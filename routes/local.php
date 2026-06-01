<?php

use CleaniqueCoders\LaravelBilling\Http\Controllers\LocalCheckoutController;
use Illuminate\Support\Facades\Route;

/*
| Dev-checkout routes for the bundled LocalGateway. Loaded only when
| config('billing.gateways.local.enabled') is true and the app is not in
| production (see the service provider).
*/

Route::middleware('web')->group(function () {
    Route::get('billing/local/checkout', [LocalCheckoutController::class, 'show'])
        ->name('billing.local.checkout');

    Route::post('billing/local/checkout', [LocalCheckoutController::class, 'callback'])
        ->name('billing.local.callback');
});
