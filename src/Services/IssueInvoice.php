<?php

namespace CleaniqueCoders\LaravelBilling\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use CleaniqueCoders\LaravelBilling\Contracts\Billable;
use CleaniqueCoders\LaravelBilling\Enums\InvoiceStatus;
use CleaniqueCoders\LaravelBilling\Events\InvoiceIssued;
use CleaniqueCoders\LaravelBilling\Mail\InvoiceIssuedMail;
use CleaniqueCoders\LaravelBilling\Models\Invoice;
use CleaniqueCoders\LaravelBilling\Models\InvoiceSequence;
use CleaniqueCoders\LaravelBilling\Models\Subscription;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Issues an invoice for a subscription period: allocates a sequential number,
 * renders & stores a PDF, persists the record and fires InvoiceIssued.
 */
class IssueInvoice
{
    protected Filesystem $disk;

    /** @var array<string,mixed> */
    protected array $config;

    /**
     * @param  array<string,mixed>|null  $config
     */
    public function __construct(?Filesystem $disk = null, ?array $config = null)
    {
        $this->config = $config ?? config('billing');
        $this->disk = $disk ?? Storage::disk($this->config['invoice']['disk'] ?? 'local');
    }

    public function __invoke(Subscription $subscription, bool $email = false): Invoice
    {
        $plan = $subscription->plan();
        $now = now();
        $year = (int) $now->format('Y');

        /** @var (Model&Billable)|null $billable */
        $billable = $subscription->billable;

        /** @var class-string<Invoice> $invoiceModel */
        $invoiceModel = $this->config['models']['invoice'] ?? Invoice::class;

        $subtotalCents = $plan?->priceCents($subscription->interval) ?? 0;

        $taxConfig = $this->config['tax'] ?? [];
        $taxEnabled = (bool) ($taxConfig['enabled'] ?? false);
        $taxRate = (float) ($taxConfig['rate'] ?? 0);
        $taxCents = $taxEnabled && $taxRate > 0 ? (int) round($subtotalCents * $taxRate) : 0;

        /** @var Invoice $invoice */
        $invoice = new $invoiceModel([
            'number' => self::nextNumber($year),
            'billable_type' => $billable?->getMorphClass(),
            'billable_id' => $billable?->getKey(),
            'subscription_id' => $subscription->getKey(),
            'plan_tier' => $subscription->plan_tier,
            'interval' => $subscription->interval,
            'period_start' => $subscription->current_period_start,
            'period_end' => $subscription->current_period_end,
            'subtotal_cents' => $subtotalCents,
            'tax_cents' => $taxCents,
            'tax_rate' => $taxCents > 0 ? $taxRate : null,
            'tax_label' => $taxCents > 0 ? ($taxConfig['label'] ?? 'SST') : null,
            'total_cents' => $subtotalCents + $taxCents,
            'currency' => $this->config['currency'] ?? 'MYR',
            'status' => InvoiceStatus::Paid,
            'issued_at' => $now,
            'paid_at' => $now,
            'metadata' => ['payment_method' => $subscription->gateway],
        ]);
        $invoice->save();

        $this->storePdf($invoice);

        InvoiceIssued::dispatch($invoice);

        if ($email && $billable !== null) {
            Mail::to($billable->billingEmail())->send(new InvoiceIssuedMail($invoice));
        }

        return $invoice;
    }

    /**
     * Allocate the next sequential invoice number for the year. Atomic and
     * row-locked.
     */
    public static function nextNumber(int $year): string
    {
        $sequenceModel = config('billing.models.invoice_sequence', InvoiceSequence::class);

        $number = DB::transaction(function () use ($year, $sequenceModel) {
            $sequenceModel::query()->firstOrCreate(['year' => $year], ['next_number' => 1]);

            $sequence = $sequenceModel::query()->where('year', $year)->lockForUpdate()->first();

            $current = (int) $sequence->next_number;
            $sequence->next_number = $current + 1;
            $sequence->save();

            return $current;
        });

        $prefix = config('billing.invoice.prefix', 'INV');
        $pad = (int) config('billing.invoice.number_pad', 6);

        return sprintf('%s-%d-%s', $prefix, $year, str_pad((string) $number, $pad, '0', STR_PAD_LEFT));
    }

    protected function storePdf(Invoice $invoice): void
    {
        $view = $this->config['invoice']['view'] ?? 'billing::invoice-pdf';

        if (! View::exists($view)) {
            return;
        }

        $html = View::make($view, [
            'invoice' => $invoice,
            'billable' => $invoice->billable,
            'company' => $this->config['company'] ?? [],
        ])->render();

        $path = $this->resolvePath($invoice);

        try {
            $contents = class_exists(Pdf::class) ? Pdf::loadHTML($html)->output() : $html;
        } catch (\Throwable $e) {
            // A rendering failure must not block invoice issuance; fall back to
            // storing the rendered HTML so the artifact still exists.
            report($e);
            $contents = $html;
        }

        $this->disk->put($path, $contents);

        $invoice->forceFill(['storage_path' => $path])->save();
    }

    protected function resolvePath(Invoice $invoice): string
    {
        $template = $this->config['invoice']['path']
            ?? 'billing/{billable_type}/{billable_id}/invoices/{invoice_uuid}.pdf';

        return strtr($template, [
            '{billable_type}' => Str::slug(str_replace('\\', '-', (string) $invoice->billable_type)),
            '{billable_id}' => (string) $invoice->billable_id,
            '{invoice_uuid}' => (string) $invoice->uuid,
        ]);
    }
}
