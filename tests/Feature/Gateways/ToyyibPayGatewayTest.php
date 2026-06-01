<?php

use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
use CleaniqueCoders\LaravelBilling\Gateways\ToyyibPayGateway;
use CleaniqueCoders\LaravelBilling\Services\PlanRepository;
use CleaniqueCoders\LaravelBilling\Tests\Fixtures\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

function toyyibGateway(): ToyyibPayGateway
{
    return new ToyyibPayGateway([
        'secret_key' => 'sk', 'category_code' => 'cat1',
        'callback_url' => 'https://app.test/webhooks/toyyibpay', 'sandbox' => true,
    ]);
}

beforeEach(function () {
    config()->set('billing.store', 'config');
    config()->set('billing.plans', [
        'pro' => ['name' => 'Pro', 'price_cents' => ['monthly' => 4900, 'annual' => 49000], 'limits' => []],
    ]);
});

it('creates a bill and returns the hosted url + bill code', function () {
    Http::fake([
        '*/api/createBill' => Http::response([['BillCode' => 'abc123']]),
    ]);

    $user = User::create(['name' => 'Ali', 'email' => 'ali@example.test']);
    $plan = app(PlanRepository::class)->find('pro');

    $intent = toyyibGateway()->createCheckout($user, $plan, PlanInterval::Monthly, 'https://app.test/done');

    expect($intent->redirectUrl)->toBe('https://dev.toyyibpay.com/abc123')
        ->and($intent->externalId)->toBe('abc123');

    Http::assertSent(fn ($req) => str_contains($req->url(), 'createBill') && (int) $req['billAmount'] === 4900);
});

it('activates only after re-querying confirms payment', function () {
    Http::fake([
        '*/api/getBillTransactions' => Http::response([['billpaymentStatus' => '1']]),
    ]);

    $event = toyyibGateway()->parseWebhook(Request::create('/cb', 'POST', [
        'billcode' => 'abc123', 'status_id' => '1', 'refno' => 'TP1',
    ]));

    expect($event->type)->toBe(WebhookEventType::SubscriptionActivated)
        ->and($event->externalId)->toBe('abc123');
});

it('does not trust a status_id=1 callback the API has not confirmed', function () {
    Http::fake([
        '*/api/getBillTransactions' => Http::response([['billpaymentStatus' => '2']]), // pending
    ]);

    expect(toyyibGateway()->parseWebhook(Request::create('/cb', 'POST', [
        'billcode' => 'abc123', 'status_id' => '1',
    ])))->toBeNull();
});

it('maps a failed callback to PaymentFailed when unpaid', function () {
    Http::fake([
        '*/api/getBillTransactions' => Http::response([['billpaymentStatus' => '3']]),
    ]);

    expect(toyyibGateway()->parseWebhook(Request::create('/cb', 'POST', [
        'billcode' => 'abc123', 'status_id' => '3',
    ]))->type)->toBe(WebhookEventType::PaymentFailed);
});
