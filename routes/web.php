<?php

use CleaniqueCoders\LaravelBilling\Http\Controllers\InvoiceController;
use CleaniqueCoders\LaravelBilling\Livewire\BillingPortal;
use CleaniqueCoders\LaravelBilling\Livewire\PaymentSuccess;
use CleaniqueCoders\LaravelBilling\Livewire\Plans;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/*
| Optional customer-facing billing UI. Registered only when
| config('billing.routes.enabled') is true and Livewire is installed. Scoped
| to the resolved billable; downloads 403 on a foreign invoice.
*/

if (config('billing.routes.enabled') && class_exists(Livewire::class)) {
    Route::middleware(config('billing.routes.middleware', ['web', 'auth']))
        ->prefix(config('billing.routes.prefix', 'billing'))
        ->group(function () {
            Route::get('/', BillingPortal::class)->name('billing.portal');
            Route::get('plans', Plans::class)->name('billing.plans');
            Route::get('success', PaymentSuccess::class)->name('billing.success');

            Route::get('invoices/{invoice:uuid}/download', [InvoiceController::class, 'download'])
                ->name('billing.invoices.download');
            Route::get('invoices/{invoice:uuid}/receipt', [InvoiceController::class, 'receipt'])
                ->name('billing.invoices.receipt');
        });
}
