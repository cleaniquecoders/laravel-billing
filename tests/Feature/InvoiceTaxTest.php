<?php

use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Facades\Billing;
use CleaniqueCoders\LaravelBilling\Services\PlanRepository;
use CleaniqueCoders\LaravelBilling\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('billing.store', 'config');
    config()->set('billing.plans', [
        'pro' => ['name' => 'Pro', 'price_cents' => ['monthly' => 4900, 'annual' => 49000], 'limits' => ['seats' => 10]],
    ]);
    config()->set('billing.gateways.local.auto', true);
    Storage::fake('local');
    Mail::fake();
});

it('computes SST tax on the issued invoice when enabled', function () {
    config()->set('billing.tax', ['enabled' => true, 'rate' => 0.08, 'label' => 'SST']);

    $user = User::create(['name' => 'Ali', 'email' => 'ali@example.test']);
    $plan = app(PlanRepository::class)->find('pro');

    Billing::checkout($user, $plan, PlanInterval::Monthly, 'https://app.test/done');

    $invoice = $user->invoices()->first();

    expect($invoice->subtotal_cents)->toBe(4900)
        ->and($invoice->tax_cents)->toBe(392)        // round(4900 * 0.08)
        ->and($invoice->total_cents)->toBe(5292)
        ->and($invoice->tax_label)->toBe('SST')
        ->and($invoice->tax_rate)->toBe(0.08);
});

it('leaves tax at zero when disabled', function () {
    config()->set('billing.tax', ['enabled' => false, 'rate' => 0.08, 'label' => 'SST']);

    $user = User::create(['name' => 'Siti', 'email' => 'siti@example.test']);
    $plan = app(PlanRepository::class)->find('pro');

    Billing::checkout($user, $plan, PlanInterval::Monthly, 'https://app.test/done');

    $invoice = $user->invoices()->first();

    expect($invoice->tax_cents)->toBe(0)
        ->and($invoice->total_cents)->toBe(4900)
        ->and($invoice->tax_label)->toBeNull();
});
