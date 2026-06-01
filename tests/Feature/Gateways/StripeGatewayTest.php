<?php

use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
use CleaniqueCoders\LaravelBilling\Gateways\StripeGateway;
use CleaniqueCoders\LaravelBilling\Services\PlanRepository;
use CleaniqueCoders\LaravelBilling\Tests\Fixtures\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

function stripeGateway(): StripeGateway
{
    return new StripeGateway([
        'secret' => 'sk_test_123',
        'webhook_secret' => 'whsec_test',
        'prices' => ['pro' => ['monthly' => 'price_pro_m', 'annual' => 'price_pro_a']],
    ]);
}

function stripeSigned(array $event, string $secret = 'whsec_test', int $t = 1700000000): Request
{
    $body = (string) json_encode($event);
    $sig = hash_hmac('sha256', $t.'.'.$body, $secret);

    return Request::create('/webhooks/stripe', 'POST', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => "t={$t},v1={$sig}",
        'CONTENT_TYPE' => 'application/json',
    ], $body);
}

beforeEach(function () {
    config()->set('billing.store', 'config');
    config()->set('billing.plans', [
        'pro' => ['name' => 'Pro', 'price_cents' => ['monthly' => 4900, 'annual' => 49000], 'limits' => []],
    ]);
});

it('creates a subscription checkout session and returns the hosted url + id', function () {
    Http::fake([
        'api.stripe.com/v1/checkout/sessions' => Http::response([
            'id' => 'cs_test_1',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_1',
        ]),
    ]);

    $user = User::create(['name' => 'Ali', 'email' => 'ali@example.test']);
    $plan = app(PlanRepository::class)->find('pro');

    $intent = stripeGateway()->createCheckout($user, $plan, PlanInterval::Monthly, 'https://app.test/done');

    expect($intent->redirectUrl)->toBe('https://checkout.stripe.com/c/pay/cs_test_1')
        ->and($intent->externalId)->toBe('cs_test_1');

    Http::assertSent(fn ($req) => str_contains($req->url(), '/v1/checkout/sessions')
        && $req['mode'] === 'subscription'
        && $req['line_items'][0]['price'] === 'price_pro_m');
});

it('maps a signed checkout.session.completed to activation', function () {
    $event = stripeSigned([
        'id' => 'evt_1',
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_test_1', 'subscription' => 'sub_1', 'amount_total' => 4900]],
    ]);

    $result = stripeGateway()->parseWebhook($event);

    expect($result)->not->toBeNull()
        ->and($result->type)->toBe(WebhookEventType::SubscriptionActivated)
        ->and($result->externalId)->toBe('cs_test_1')
        ->and($result->amountCents)->toBe(4900)
        ->and($result->rawPayload['data']['object']['subscription'])->toBe('sub_1');
});

it('maps a signed invoice.paid to renewal keyed by subscription id', function () {
    $result = stripeGateway()->parseWebhook(stripeSigned([
        'id' => 'evt_2',
        'type' => 'invoice.paid',
        'data' => ['object' => ['subscription' => 'sub_1', 'amount_paid' => 4900]],
    ]));

    expect($result->type)->toBe(WebhookEventType::SubscriptionRenewed)
        ->and($result->externalId)->toBe('sub_1');
});

it('rejects a webhook with a bad signature', function () {
    $body = (string) json_encode(['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => []]]);
    $request = Request::create('/webhooks/stripe', 'POST', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => 't=1700000000,v1=deadbeef',
        'CONTENT_TYPE' => 'application/json',
    ], $body);

    expect(stripeGateway()->parseWebhook($request))->toBeNull();
});
