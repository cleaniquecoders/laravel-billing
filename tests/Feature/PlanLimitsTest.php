<?php

use CleaniqueCoders\LaravelBilling\Models\Plan;
use CleaniqueCoders\LaravelBilling\Models\Subscription;
use CleaniqueCoders\LaravelBilling\Tests\Fixtures\Models\User;

function subscribeUserTo(string $tier): User
{
    $user = User::create(['name' => 'Ali', 'email' => 'ali@example.test']);

    Subscription::factory()->create([
        'billable_type' => $user->getMorphClass(),
        'billable_id' => $user->getKey(),
        'plan_tier' => $tier,
    ]);

    return $user;
}

it('enforces metered limits with the config store', function () {
    config()->set('billing.store', 'config');
    config()->set('billing.plans', [
        'team' => ['name' => 'Team', 'price_cents' => ['monthly' => 0, 'annual' => 0], 'limits' => ['messages_per_month' => 3]],
    ]);

    $user = subscribeUserTo('team');

    expect($user->canConsume('messages_per_month', 1))->toBeTrue();

    $user->recordUsage('messages_per_month', 3);

    expect($user->canConsume('messages_per_month', 1))->toBeFalse()
        ->and($user->canConsume('api_calls'))->toBeTrue(); // unmetered = unlimited
});

it('enforces metered limits with the database store', function () {
    config()->set('billing.store', 'database');

    Plan::create([
        'tier' => 'team',
        'name' => 'Team',
        'price_cents' => ['monthly' => 0, 'annual' => 0],
        'limits' => ['seats' => 2],
        'features' => [],
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $user = subscribeUserTo('team');

    expect($user->plan()->tier)->toBe('team')
        ->and($user->canConsume('seats', 2))->toBeTrue();

    $user->recordUsage('seats', 2);

    expect($user->canConsume('seats', 1))->toBeFalse();
});

it('treats a null limit as unlimited', function () {
    config()->set('billing.store', 'config');
    config()->set('billing.plans', [
        'unlimited' => ['name' => 'Unlimited', 'price_cents' => ['monthly' => 0, 'annual' => 0], 'limits' => ['seats' => null]],
    ]);

    $user = subscribeUserTo('unlimited');
    $user->recordUsage('seats', 9999);

    expect($user->canConsume('seats', 1))->toBeTrue();
});
