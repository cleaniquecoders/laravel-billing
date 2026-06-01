<?php

use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
use CleaniqueCoders\LaravelBilling\Gateways\BayarCashGateway;
use CleaniqueCoders\LaravelBilling\Services\PlanRepository;
use CleaniqueCoders\LaravelBilling\Tests\Fixtures\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

function bayarCashGateway(): BayarCashGateway
{
    return new BayarCashGateway([
        'pat' => 'pat_token', 'portal_key' => 'portal1', 'api_secret_key' => 'apisecret',
        'callback_url' => 'https://app.test/webhooks/bayarcash',
        'api_url' => 'https://api.console.bayar.cash/v3',
    ]);
}

/** Build a callback with a valid checksum over the driver's checksum field order. */
function bayarCashCallback(array $fields, string $key = 'apisecret'): Request
{
    $keys = ['transaction_id', 'exchange_reference_number', 'order_number', 'currency', 'amount', 'payer_name', 'payer_email', 'status'];
    $source = collect($keys)->map(fn ($k) => (string) ($fields[$k] ?? ''))->implode('|');
    $fields['checksum'] = hash_hmac('sha256', $source, $key);

    return Request::create('/cb', 'POST', $fields);
}

beforeEach(function () {
    config()->set('billing.store', 'config');
    config()->set('billing.plans', [
        'pro' => ['name' => 'Pro', 'price_cents' => ['monthly' => 4900, 'annual' => 49000], 'limits' => []],
    ]);
});

it('creates a payment intent and returns the hosted url + order number', function () {
    Http::fake([
        '*/payment-intents' => Http::response(['url' => 'https://console.bayar.cash/pay/abc', 'id' => 'pi_1']),
    ]);

    $user = User::create(['name' => 'Ali', 'email' => 'ali@example.test']);
    $plan = app(PlanRepository::class)->find('pro');

    $intent = bayarCashGateway()->createCheckout($user, $plan, PlanInterval::Monthly, 'https://app.test/done');

    expect($intent->redirectUrl)->toBe('https://console.bayar.cash/pay/abc')
        ->and($intent->externalId)->toStartWith('SUB');

    Http::assertSent(fn ($req) => str_contains($req->url(), '/v3/payment-intents')
        && $req['portal_key'] === 'portal1');
});

it('activates on a status=3 callback with a valid checksum', function () {
    $event = bayarCashGateway()->parseWebhook(bayarCashCallback([
        'transaction_id' => 'TX1', 'exchange_reference_number' => 'EX1', 'order_number' => 'SUBX',
        'currency' => 'MYR', 'amount' => '49.00', 'payer_name' => 'Ali', 'payer_email' => 'ali@example.test',
        'status' => '3',
    ]));

    expect($event->type)->toBe(WebhookEventType::SubscriptionActivated)
        ->and($event->externalId)->toBe('SUBX')
        ->and($event->amountCents)->toBe(4900);
});

it('maps status=2 to PaymentFailed and status=4 to cancellation', function () {
    $failed = bayarCashGateway()->parseWebhook(bayarCashCallback(['order_number' => 'SUBX', 'amount' => '49.00', 'status' => '2']));
    $cancelled = bayarCashGateway()->parseWebhook(bayarCashCallback(['order_number' => 'SUBX', 'status' => '4']));

    expect($failed->type)->toBe(WebhookEventType::PaymentFailed)
        ->and($cancelled->type)->toBe(WebhookEventType::SubscriptionCanceled);
});

it('ignores pending statuses and rejects a bad checksum', function () {
    $pending = bayarCashGateway()->parseWebhook(bayarCashCallback(['order_number' => 'SUBX', 'status' => '1']));
    $tampered = bayarCashGateway()->parseWebhook(bayarCashCallback(['order_number' => 'SUBX', 'status' => '3'], 'WRONG'));

    expect($pending)->toBeNull()->and($tampered)->toBeNull();
});
