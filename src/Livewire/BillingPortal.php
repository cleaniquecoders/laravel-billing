<?php

namespace CleaniqueCoders\LaravelBilling\Livewire;

use CleaniqueCoders\LaravelBilling\Contracts\Billable;
use CleaniqueCoders\LaravelBilling\Facades\Billing;
use CleaniqueCoders\LaravelBilling\Support\BillableResolver;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The customer billing portal: Overview (subscription + cancel/resume) and an
 * Invoices tab with a detail panel. Scoped to the resolved billable.
 */
class BillingPortal extends Component
{
    public string $tab = 'overview';

    public ?string $selectedInvoiceUuid = null;

    public bool $showCancelModal = false;

    public function selectInvoice(string $uuid): void
    {
        $this->selectedInvoiceUuid = $uuid;
    }

    public function clearInvoice(): void
    {
        $this->selectedInvoiceUuid = null;
    }

    public function confirmCancel(): void
    {
        $this->showCancelModal = true;
    }

    public function cancel(): void
    {
        if ($subscription = $this->billable()?->subscription()) {
            Billing::cancel($subscription, atPeriodEnd: true);
        }

        $this->showCancelModal = false;
    }

    public function resume(): void
    {
        if ($subscription = $this->billable()?->subscription()) {
            Billing::resume($subscription);
        }
    }

    protected function billable(): ?Billable
    {
        return app(BillableResolver::class)->resolve();
    }

    public function render(): View
    {
        $billable = $this->billable();
        abort_if($billable === null, 403);

        $invoices = $billable->invoices()->get();

        return view('billing::livewire.billing-portal', [
            'subscription' => $billable->subscription(),
            'plan' => $billable->plan(),
            'invoices' => $invoices,
            'selectedInvoice' => $this->selectedInvoiceUuid
                ? $invoices->firstWhere('uuid', $this->selectedInvoiceUuid)
                : null,
            'currency' => config('billing.currency', 'MYR'),
        ])->layout(config('billing.layout', 'billing::layouts.app'));
    }
}
