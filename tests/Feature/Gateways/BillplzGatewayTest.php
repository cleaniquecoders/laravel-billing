<?php

use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
use CleaniqueCoders\LaravelBilling\Gateways\BillplzGateway;
use CleaniqueCoders\LaravelBilling\Services\PlanRepository;
use CleaniqueCoders\LaravelBilling\Tests\Fixtures\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

function billplzGateway(): BillplzGateway
{
    return new BillplzGateway([
        'api_key' => 'key', 'x_signature_key' => 'xsig', 'collection_id' => 'col1',
        'callback_url' => 'https://app.test/webhooks/billplz', 'sandbox' => true,
    ]);
}

/** Build a callback with a correct X Signature (HMAC-SHA256 over sorted key+value | joined). */
function billplzCallback(array $fields, string $key = 'xsig'): Request
{
    $source = collect($fields)->map(fn ($v, $k) => $k.$v)->sort()->implode('|');
    $fields['x_signature'] = hash_hmac('sha256', $source, $key);

    return Request::create('/cb', 'POST', $fields);
}

beforeEach(function () {
    config()->set('billing.store', 'config');
    config()->set('billing.plans', [
        'pro' => ['name' => 'Pro', 'price_cents' => ['monthly' => 4900, 'annual' => 49000], 'limits' => []],
    ]);
});

it('creates a bill and returns its url + id', function () {
    Http::fake([
        '*/api/v3/bills' => Http::response(['id' => 'bill_1', 'url' => 'https://www.billplz-sandbox.com/bills/bill_1']),
    ]);

    $user = User::create(['name' => 'Ali', 'email' => 'ali@example.test']);
    $plan = app(PlanRepository::class)->find('pro');

    $intent = billplzGateway()->createCheckout($user, $plan, PlanInterval::Monthly, 'https://app.test/done');

    expect($intent->redirectUrl)->toBe('https://www.billplz-sandbox.com/bills/bill_1')
        ->and($intent->externalId)->toBe('bill_1');

    Http::assertSent(fn ($req) => str_contains($req->url(), 'billplz-sandbox.com/api/v3/bills')
        && (int) $req['amount'] === 4900);
});

it('activates on a paid, correctly signed callback', function () {
    $request = billplzCallback([
        'id' => 'bill_1', 'collection_id' => 'col1', 'paid' => 'true',
        'state' => 'paid', 'amount' => '4900', 'paid_amount' => '4900',
    ]);

    $event = billplzGateway()->parseWebhook($request);

    expect($event->type)->toBe(WebhookEventType::SubscriptionActivated)
        ->and($event->externalId)->toBe('bill_1')
        ->and($event->amountCents)->toBe(4900);
});

it('maps an unpaid callback to PaymentFailed', function () {
    $request = billplzCallback(['id' => 'bill_1', 'paid' => 'false', 'amount' => '4900']);

    expect(billplzGateway()->parseWebhook($request)->type)->toBe(WebhookEventType::PaymentFailed);
});

it('rejects a callback with a wrong X Signature', function () {
    $request = billplzCallback(['id' => 'bill_1', 'paid' => 'true', 'amount' => '4900'], 'WRONG-KEY');

    expect(billplzGateway()->parseWebhook($request))->toBeNull();
});
