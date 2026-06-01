<?php

use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Enums\SubscriptionStatus;
use CleaniqueCoders\LaravelBilling\Facades\Billing;
use CleaniqueCoders\LaravelBilling\Gateways\LocalGateway;
use CleaniqueCoders\LaravelBilling\Services\PlanRepository;
use CleaniqueCoders\LaravelBilling\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('billing.store', 'config');
    config()->set('billing.plans', [
        'free' => ['name' => 'Free', 'price_cents' => ['monthly' => 0, 'annual' => 0], 'limits' => ['seats' => 1]],
        'pro' => ['name' => 'Pro', 'price_cents' => ['monthly' => 4900, 'annual' => 49000], 'limits' => ['seats' => 10]],
    ]);
    config()->set('billing.gateways.local.auto', false);
    config()->set('billing.gateways.local.enabled', true);
    Storage::fake('local');
    Mail::fake();
});

it('redirects to the dev checkout page when not auto', function () {
    $user = User::create(['name' => 'Ali', 'email' => 'ali@example.test']);
    $plan = app(PlanRepository::class)->find('pro');

    $intent = Billing::checkout($user, $plan, PlanInterval::Monthly, 'https://app.test/done');

    expect($intent->redirectUrl)->toContain('billing/local/checkout')
        ->and($user->subscription())->toBeNull(); // still incomplete, no access
});

it('activates the subscription when the dev checkout is approved', function () {
    $user = User::create(['name' => 'Ali', 'email' => 'ali@example.test']);
    $plan = app(PlanRepository::class)->find('pro');

    $intent = Billing::checkout($user, $plan, PlanInterval::Monthly, 'https://app.test/done');
    $token = (string) str(parse_url($intent->redirectUrl, PHP_URL_QUERY))->after('token=');
    $token = urldecode($token);

    $response = $this->post(route('billing.local.callback'), [
        'token' => $token,
        'decision' => 'approve',
    ]);

    $response->assertRedirect('https://app.test/done');

    expect($user->subscription()?->status)->toBe(SubscriptionStatus::Active);
});

it('leaves the subscription incomplete when declined', function () {
    $user = User::create(['name' => 'Ali', 'email' => 'ali@example.test']);
    $plan = app(PlanRepository::class)->find('pro');

    $intent = Billing::checkout($user, $plan, PlanInterval::Monthly, 'https://app.test/done');
    $token = urldecode((string) str(parse_url($intent->redirectUrl, PHP_URL_QUERY))->after('token='));

    $this->post(route('billing.local.callback'), [
        'token' => $token,
        'decision' => 'decline',
    ]);

    expect($user->subscription())->toBeNull()
        ->and($user->subscriptions()->first()->status)->toBe(SubscriptionStatus::Incomplete);
});

it('rejects a tampered token signature', function () {
    $token = LocalGateway::sign(['external_id' => 'x', 'amount_cents' => 100]);

    expect(LocalGateway::verify($token))->not->toBeNull()
        ->and(LocalGateway::verify($token.'tampered'))->toBeNull();
});
