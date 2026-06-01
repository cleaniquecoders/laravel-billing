<?php

use CleaniqueCoders\LaravelBilling\Livewire\Plans;
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

it('subscribes the authenticated billable through the plans component', function () {
    $user = User::create(['name' => 'Ali', 'email' => 'ali@example.test']);

    Livewire::actingAs($user)
        ->test(Plans::class)
        ->set('interval', 'monthly')
        ->call('subscribe', 'pro')
        ->assertRedirect();

    $user->refresh();

    expect($user->subscription())->not->toBeNull()
        ->and($user->subscribedTo('pro'))->toBeTrue();
});

it('marks the active tier as the current plan', function () {
    $user = User::create(['name' => 'Siti', 'email' => 'siti@example.test']);

    Livewire::actingAs($user)
        ->test(Plans::class)
        ->call('subscribe', 'pro');

    Livewire::actingAs($user->fresh())
        ->test(Plans::class)
        ->assertSee('Current plan');
});
