<?php

use CleaniqueCoders\LaravelBilling\Contracts\PaymentGateway;
use CleaniqueCoders\LaravelBilling\Facades\Billing;
use CleaniqueCoders\LaravelBilling\Gateways\StripeGateway;

it('resolves a class driver and injects its config block', function () {
    config()->set('billing.gateways.stripe', [
        'driver' => StripeGateway::class,
        'secret' => 'sk_test_x',
        'webhook_secret' => 'whsec_x',
        'prices' => ['pro' => ['monthly' => 'price_x']],
    ]);

    $driver = Billing::gateway('stripe');

    // Successful construction proves the config array was injected — the driver
    // requires `array $config` and the container could not autowire it otherwise.
    expect($driver)->toBeInstanceOf(StripeGateway::class)
        ->and($driver)->toBeInstanceOf(PaymentGateway::class);
});

it('throws for an unconfigured gateway', function () {
    Billing::gateway('does-not-exist');
})->throws(InvalidArgumentException::class);
