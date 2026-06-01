<?php

use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Facades\Billing;
use CleaniqueCoders\LaravelBilling\Livewire\BillingPortal;
use CleaniqueCoders\LaravelBilling\Services\PlanRepository;
use CleaniqueCoders\LaravelBilling\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('billing.store', 'config');
    config()->set('billing.plans', [
        'free' => ['name' => 'Free', 'price_cents' => ['monthly' => 0, 'annual' => 0], 'limits' => ['seats' => 1]],
        'pro' => ['name' => 'Pro', 'price_cents' => ['monthly' => 4900, 'annual' => 49000], 'limits' => ['seats' => 10]],
    ]);
    config()->set('billing.gateways.local.auto', true);
    Storage::fake('local');
    Mail::fake();
});

function subscribeUser(string $email): User
{
    $user = User::create(['name' => 'User', 'email' => $email]);
    $plan = app(PlanRepository::class)->find('pro');
    Billing::checkout($user, $plan, PlanInterval::Monthly, 'https://app.test/done');

    return $user->fresh();
}

it('scopes invoice details to the billable and ignores foreign ones', function () {
    $mine = subscribeUser('mine@example.test');
    $other = subscribeUser('other@example.test');

    $myInvoice = $mine->invoices()->first();
    $otherInvoice = $other->invoices()->first();

    // Own invoice resolves and its number shows in the detail panel; a foreign
    // uuid resolves to nothing, so its number never appears.
    Livewire::actingAs($mine)
        ->test(BillingPortal::class)
        ->set('tab', 'invoices')
        ->call('selectInvoice', $myInvoice->uuid)
        ->assertSee($myInvoice->number)
        ->call('selectInvoice', $otherInvoice->uuid)
        ->assertDontSee($otherInvoice->number);
});

it('cancels at period end and resumes', function () {
    $user = subscribeUser('grace@example.test');

    Livewire::actingAs($user)
        ->test(BillingPortal::class)
        ->call('confirmCancel')
        ->assertSet('showCancelModal', true)
        ->call('cancel')
        ->assertSet('showCancelModal', false);

    expect($user->subscription()->cancel_at_period_end)->toBeTrue();

    Livewire::actingAs($user)
        ->test(BillingPortal::class)
        ->call('resume');

    expect($user->subscription()->cancel_at_period_end)->toBeFalse();
});

it('opens an invoice detail panel', function () {
    $user = subscribeUser('detail@example.test');
    $invoice = $user->invoices()->first();

    Livewire::actingAs($user)
        ->test(BillingPortal::class)
        ->set('tab', 'invoices')
        ->call('selectInvoice', $invoice->uuid)
        ->assertSet('selectedInvoiceUuid', $invoice->uuid)
        ->assertSee('Cost breakdown')
        ->call('clearInvoice')
        ->assertSet('selectedInvoiceUuid', null);
});
