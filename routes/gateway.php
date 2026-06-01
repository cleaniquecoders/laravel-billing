<?php

use CleaniqueCoders\LaravelBilling\Http\Controllers\GatewayRedirectController;
use Illuminate\Support\Facades\Route;

/*
| Gateway redirect bridge. Renders an auto-submitting form that POSTs signed
| fields to a form-POST gateway's entry URL (iPay88, eGHL, senangPay). Needed
| whenever such a gateway is used — including headless installs without the
| Livewire UI — so it is registered independently of the UI routes, gated only
| by the master billing.routes.enabled switch. The token is app-key-signed, so
| the endpoint needs no auth of its own.
*/

if (config('billing.routes.enabled', true)) {
    Route::middleware('web')
        ->prefix(config('billing.routes.prefix', 'billing'))
        ->group(function () {
            Route::get('redirect', GatewayRedirectController::class)
                ->name('billing.gateway.redirect');
        });
}
