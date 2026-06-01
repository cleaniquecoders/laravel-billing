<?php

namespace CleaniqueCoders\LaravelBilling\Livewire;

use CleaniqueCoders\LaravelBilling\Enums\InvoiceStatus;
use CleaniqueCoders\LaravelBilling\Support\BillableResolver;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Post-checkout receipt card. Shows the paid invoice with download links for
 * the invoice and receipt PDFs.
 */
class PaymentSuccess extends Component
{
    public ?string $invoice = null;

    public function mount(): void
    {
        $this->invoice = request()->query('invoice');
    }

    public function render(): View
    {
        $billable = app(BillableResolver::class)->resolve();
        abort_if($billable === null, 403);

        $invoice = $this->invoice
            ? $billable->invoices()->where('uuid', $this->invoice)->first()
            : $billable->invoices()->where('status', InvoiceStatus::Paid->value)->first();

        return view('billing::livewire.payment-success', [
            'invoice' => $invoice,
        ])->layout(config('billing.layout', 'billing::layouts.app'));
    }
}
