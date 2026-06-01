<?php

use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Enums\SubscriptionStatus;
use CleaniqueCoders\LaravelBilling\Events\SubscriptionActivated;
use CleaniqueCoders\LaravelBilling\Facades\Billing;
use CleaniqueCoders\LaravelBilling\Models\Invoice;
use CleaniqueCoders\LaravelBilling\Services\PlanRepository;
use CleaniqueCoders\LaravelBilling\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

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

it('subscribes a billable end-to-end through the local gateway (auto)', function () {
    Event::fake([SubscriptionActivated::class]);

    $user = User::create(['name' => 'Ali', 'email' => 'ali@example.test']);
    $plan = app(PlanRepository::class)->find('pro');

    $intent = Billing::checkout($user, $plan, PlanInterval::Monthly, 'https://app.test/done');

    expect($intent->externalId)->not->toBeEmpty();

    $subscription = $user->subscription();

    expect($subscription)->not->toBeNull()
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->plan_tier)->toBe('pro')
        ->and($user->subscribedTo('pro'))->toBeTrue();

    Event::assertDispatched(SubscriptionActivated::class);

    expect(Invoice::where('billable_id', $user->id)->count())->toBe(1);
});

it('resolves the default plan when there is no subscription', function () {
    $user = User::create(['name' => 'Siti', 'email' => 'siti@example.test']);

    expect($user->subscription())->toBeNull()
        ->and($user->plan()->tier)->toBe('free');
});
