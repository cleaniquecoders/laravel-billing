<?php

namespace CleaniqueCoders\LaravelBilling\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use CleaniqueCoders\LaravelBilling\Models\Invoice;
use CleaniqueCoders\LaravelBilling\Services\GenerateReceipt;
use CleaniqueCoders\LaravelBilling\Support\BillableResolver;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

/**
 * Streams customer-facing invoice and receipt PDFs, scoped to the resolved
 * billable. The invoice PDF is served from storage (rendered on the fly if
 * missing); the receipt is always derived from the paid invoice.
 */
class InvoiceController extends Controller
{
    public function __construct(protected BillableResolver $resolver) {}

    public function download(Request $request, Invoice $invoice)
    {
        $this->authorizeInvoice($request, $invoice);

        $disk = Storage::disk(config('billing.invoice.disk', 'local'));

        if ($invoice->storage_path && $disk->exists($invoice->storage_path)) {
            return $disk->download($invoice->storage_path, $invoice->number.'.pdf');
        }

        return response($this->renderInvoice($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice->number.'.pdf"',
        ]);
    }

    public function receipt(Request $request, Invoice $invoice, GenerateReceipt $receipt)
    {
        $this->authorizeInvoice($request, $invoice);

        abort_unless($invoice->isPaid(), 404);

        return response($receipt($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$receipt->filename($invoice).'"',
        ]);
    }

    protected function authorizeInvoice(Request $request, Invoice $invoice): void
    {
        $billable = $this->resolver->resolve($request);

        abort_if($billable === null, 403);

        abort_unless(
            $invoice->billable_type === $billable->getMorphClass()
                && (string) $invoice->billable_id === (string) $billable->getKey(),
            403
        );
    }

    protected function renderInvoice(Invoice $invoice): string
    {
        $html = View::make(config('billing.invoice.view', 'billing::invoice-pdf'), [
            'invoice' => $invoice,
            'billable' => $invoice->billable,
            'company' => config('billing.company', []),
        ])->render();

        return class_exists(Pdf::class) ? Pdf::loadHTML($html)->output() : $html;
    }
}
