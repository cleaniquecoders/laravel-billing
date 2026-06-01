<?php

use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
use CleaniqueCoders\LaravelBilling\Gateways\PayPalGateway;
use CleaniqueCoders\LaravelBilling\Services\PlanRepository;
use CleaniqueCoders\LaravelBilling\Tests\Fixtures\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

function payPalGateway(): PayPalGateway
{
    return new PayPalGateway([
        'client_id' => 'id', 'client_secret' => 'secret', 'webhook_id' => 'wh_1',
        'mode' => 'sandbox',
        'plans' => ['pro' => ['monthly' => 'P-PRO-M', 'annual' => 'P-PRO-A']],
    ]);
}

function payPalWebhook(array $event): Request
{
    return Request::create('/webhooks/paypal', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PAYPAL_TRANSMISSION_ID' => 'tx', 'HTTP_PAYPAL_TRANSMISSION_SIG' => 'sig',
        'HTTP_PAYPAL_TRANSMISSION_TIME' => 't', 'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
        'HTTP_PAYPAL_CERT_URL' => 'https://paypal/cert',
    ], (string) json_encode($event));
}

beforeEach(function () {
    config()->set('billing.store', 'config');
    config()->set('billing.plans', [
        'pro' => ['name' => 'Pro', 'price_cents' => ['monthly' => 4900, 'annual' => 49000], 'limits' => []],
    ]);
});

it('creates a subscription and returns the approval link + subscription id', function () {
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
        '*/v1/billing/subscriptions' => Http::response([
            'id' => 'I-SUB1',
            'links' => [
                ['rel' => 'self', 'href' => 'https://x/self'],
                ['rel' => 'approve', 'href' => 'https://paypal/approve/I-SUB1'],
            ],
        ]),
    ]);

    $user = User::create(['name' => 'Ali', 'email' => 'ali@example.test']);
    $plan = app(PlanRepository::class)->find('pro');

    $intent = payPalGateway()->createCheckout($user, $plan, PlanInterval::Monthly, 'https://app.test/done');

    expect($intent->redirectUrl)->toBe('https://paypal/approve/I-SUB1')
        ->and($intent->externalId)->toBe('I-SUB1');
});

it('maps a verified activation to SubscriptionActivated', function () {
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
        '*/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
    ]);

    $result = payPalGateway()->parseWebhook(payPalWebhook([
        'id' => 'WH-1',
        'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
        'resource' => ['id' => 'I-SUB1'],
    ]));

    expect($result->type)->toBe(WebhookEventType::SubscriptionActivated)
        ->and($result->externalId)->toBe('I-SUB1');
});

it('maps a verified PAYMENT.SALE.COMPLETED to renewal keyed by the subscription id', function () {
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
        '*/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
    ]);

    $result = payPalGateway()->parseWebhook(payPalWebhook([
        'id' => 'WH-2',
        'event_type' => 'PAYMENT.SALE.COMPLETED',
        'resource' => ['billing_agreement_id' => 'I-SUB1', 'amount' => ['total' => '49.00']],
    ]));

    expect($result->type)->toBe(WebhookEventType::SubscriptionRenewed)
        ->and($result->externalId)->toBe('I-SUB1')
        ->and($result->amountCents)->toBe(4900);
});

it('rejects a webhook PayPal does not verify', function () {
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
        '*/verify-webhook-signature' => Http::response(['verification_status' => 'FAILURE']),
    ]);

    expect(payPalGateway()->parseWebhook(payPalWebhook([
        'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED', 'resource' => ['id' => 'I-SUB1'],
    ])))->toBeNull();
});
