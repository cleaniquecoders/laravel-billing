<?php

namespace CleaniqueCoders\LaravelBilling\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use CleaniqueCoders\LaravelBilling\Models\Invoice;
use Illuminate\Support\Facades\View;
use RuntimeException;

/**
 * Renders a payment receipt PDF on the fly from a paid invoice. Receipts are
 * derived (never stored) — only the invoice is persisted.
 */
class GenerateReceipt
{
    /** @var array<string,mixed> */
    protected array $config;

    /**
     * @param  array<string,mixed>|null  $config
     */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? config('billing');
    }

    /**
     * Render the receipt PDF and return its raw bytes.
     */
    public function __invoke(Invoice $invoice): string
    {
        if (! $invoice->isPaid()) {
            throw new RuntimeException('A receipt can only be generated for a paid invoice.');
        }

        $view = $this->config['invoice']['receipt_view'] ?? 'billing::receipt-pdf';

        $html = View::make($view, [
            'invoice' => $invoice,
            'billable' => $invoice->billable,
            'company' => $this->config['company'] ?? [],
        ])->render();

        if (class_exists(Pdf::class)) {
            return Pdf::loadHTML($html)->output();
        }

        return $html;
    }

    /**
     * A stable download filename for this receipt.
     */
    public function filename(Invoice $invoice): string
    {
        return 'receipt-'.$invoice->number.'.pdf';
    }
}
