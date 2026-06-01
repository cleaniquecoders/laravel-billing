<?php

use CleaniqueCoders\LaravelBilling\Enums\InvoiceStatus;
use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Facades\Billing;
use CleaniqueCoders\LaravelBilling\Models\Invoice;
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

function paidInvoiceFor(string $email): array
{
    $user = User::create(['name' => 'User', 'email' => $email]);
    $plan = app(PlanRepository::class)->find('pro');
    Billing::checkout($user, $plan, PlanInterval::Monthly, 'https://app.test/done');

    return [$user->fresh(), $user->invoices()->first()];
}

it('lets the owner download the invoice PDF', function () {
    [$user, $invoice] = paidInvoiceFor('owner@example.test');

    $this->actingAs($user)
        ->get(route('billing.invoices.download', $invoice->uuid))
        ->assertOk();
});

it('forbids downloading another billable invoice', function () {
    [, $invoice] = paidInvoiceFor('owner2@example.test');
    $intruder = User::create(['name' => 'Eve', 'email' => 'eve@example.test']);

    $this->actingAs($intruder)
        ->get(route('billing.invoices.download', $invoice->uuid))
        ->assertForbidden();
});

it('lets the owner download a receipt for a paid invoice', function () {
    [$user, $invoice] = paidInvoiceFor('receipt@example.test');

    $this->actingAs($user)
        ->get(route('billing.invoices.receipt', $invoice->uuid))
        ->assertOk();
});

it('returns 404 for a receipt of an unpaid invoice', function () {
    $user = User::create(['name' => 'Unpaid', 'email' => 'unpaid@example.test']);

    $invoice = new Invoice([
        'number' => 'INV-2026-000999',
        'billable_type' => $user->getMorphClass(),
        'billable_id' => $user->getKey(),
        'plan_tier' => 'pro',
        'interval' => PlanInterval::Monthly,
        'period_start' => now(),
        'period_end' => now()->addMonth(),
        'subtotal_cents' => 4900,
        'tax_cents' => 0,
        'total_cents' => 4900,
        'currency' => 'MYR',
        'status' => InvoiceStatus::Void,
        'issued_at' => now(),
    ]);
    $invoice->save();

    $this->actingAs($user)
        ->get(route('billing.invoices.receipt', $invoice->uuid))
        ->assertNotFound();
});
