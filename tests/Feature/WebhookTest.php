<?php

use CleaniqueCoders\LaravelBilling\DataTransferObjects\WebhookEvent;
use CleaniqueCoders\LaravelBilling\Enums\SubscriptionStatus;
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
use CleaniqueCoders\LaravelBilling\Events\PaymentFailed;
use CleaniqueCoders\LaravelBilling\Events\SubscriptionCanceled;
use CleaniqueCoders\LaravelBilling\Facades\Billing;
use CleaniqueCoders\LaravelBilling\Models\Invoice;
use CleaniqueCoders\LaravelBilling\Models\Subscription;
use CleaniqueCoders\LaravelBilling\Tests\Fixtures\Gateways\ArrayGateway;
use CleaniqueCoders\LaravelBilling\Tests\Fixtures\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('billing.store', 'config');
    config()->set('billing.plans', [
        'pro' => ['name' => 'Pro', 'price_cents' => ['monthly' => 4900, 'annual' => 49000], 'limits' => []],
    ]);
    Storage::fake('local');
    Mail::fake();
});

function makeIncompleteSubscription(): Subscription
{
    $user = User::create(['name' => 'Ali', 'email' => 'ali@example.test']);

    return Subscription::factory()
        ->incomplete()
        ->create([
            'billable_type' => $user->getMorphClass(),
            'billable_id' => $user->getKey(),
            'plan_tier' => 'pro',
            'gateway' => 'array',
            'gateway_subscription_id' => 'ext_123',
        ]);
}

it('activates and issues an invoice on a normalised webhook', function () {
    $subscription = makeIncompleteSubscription();

    Billing::handle(new WebhookEvent(
        type: WebhookEventType::SubscriptionActivated,
        externalId: 'ext_123',
        amountCents: 4900,
        providerEventId: 'evt_1',
    ));

    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and(Invoice::count())->toBe(1);
});

it('guards against replayed events by providerEventId', function () {
    makeIncompleteSubscription();

    $event = new WebhookEvent(
        type: WebhookEventType::SubscriptionActivated,
        externalId: 'ext_123',
        amountCents: 4900,
        providerEventId: 'evt_dup',
    );

    Billing::handle($event);
    Billing::handle($event); // replay

    expect(Invoice::count())->toBe(1);
});

it('marks the subscription past due on payment failure', function () {
    $subscription = makeIncompleteSubscription();
    Event::fake([PaymentFailed::class]);

    Billing::handle(new WebhookEvent(
        type: WebhookEventType::PaymentFailed,
        externalId: 'ext_123',
        providerEventId: 'evt_fail',
    ));

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::PastDue);
    Event::assertDispatched(PaymentFailed::class);
});

it('cancels the subscription on a cancel webhook', function () {
    $subscription = makeIncompleteSubscription();
    Event::fake([SubscriptionCanceled::class]);

    Billing::handle(new WebhookEvent(
        type: WebhookEventType::SubscriptionCanceled,
        externalId: 'ext_123',
        providerEventId: 'evt_cancel',
    ));

    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Canceled)
        ->and($subscription->canceled_at)->not->toBeNull();
    Event::assertDispatched(SubscriptionCanceled::class);
});

it('parses an inbound request through a custom gateway and dispatches it', function () {
    $gateway = new ArrayGateway;
    Billing::extend('array', fn () => $gateway);

    $subscription = makeIncompleteSubscription();

    $request = Request::create('/webhooks/array', 'POST', [
        'signature' => 'valid',
        'type' => WebhookEventType::SubscriptionActivated->value,
        'external_id' => 'ext_123',
        'amount_cents' => 4900,
        'event_id' => 'evt_parsed',
    ]);

    $event = Billing::gateway('array')->parseWebhook($request);

    expect($event)->not->toBeNull();

    Billing::handle($event);

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Active);
});

it('returns null for an invalid signature', function () {
    $gateway = new ArrayGateway;
    Billing::extend('array', fn () => $gateway);

    $request = Request::create('/webhooks/array', 'POST', ['signature' => 'nope']);

    expect(Billing::gateway('array')->parseWebhook($request))->toBeNull();
});
