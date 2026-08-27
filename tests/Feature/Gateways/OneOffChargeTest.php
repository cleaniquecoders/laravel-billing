<?php

use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutRequest;
use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Exceptions\UnsupportedByGateway;
use CleaniqueCoders\LaravelBilling\Gateways\BillplzGateway;
use CleaniqueCoders\LaravelBilling\Gateways\LocalGateway;
use CleaniqueCoders\LaravelBilling\Gateways\SenangPayGateway;
use CleaniqueCoders\LaravelBilling\Gateways\StripeGateway;
use CleaniqueCoders\LaravelBilling\Gateways\ToyyibPayGateway;
use CleaniqueCoders\LaravelBilling\Services\PlanRepository;
use CleaniqueCoders\LaravelBilling\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * The one-off charge primitive.
 *
 * Every bundled driver except Stripe and PayPal already created a **one-off
 * bill** — the plan and the interval were only ever used to derive an amount,
 * a description and a payer. Typing the contract on `Plan` and `Billable`
 * coupled ten drivers to this package's subscription tables for no reason and
 * made them unusable for the other thing a host application always needs:
 * charging for an invoice.
 *
 * So the one-off charge is now the primitive, and a plan checkout is a one-off
 * charge described by a plan. The most important test here is the one that
 * proves that mapping did not change what a plan checkout sends.
 */
beforeEach(function () {
    config()->set('billing.store', 'config');
    config()->set('billing.currency', 'MYR');
    config()->set('billing.plans', [
        'pro' => ['name' => 'Pro', 'price_cents' => ['monthly' => 4900, 'annual' => 49000], 'limits' => []],
    ]);
});

function chargeRequest(array $overrides = []): CheckoutRequest
{
    return new CheckoutRequest(
        amountCents: $overrides['amountCents'] ?? 12500,
        description: $overrides['description'] ?? 'Invoice INV-1042',
        customerName: $overrides['customerName'] ?? 'Ali bin Abu',
        customerEmail: $overrides['customerEmail'] ?? 'ali@example.test',
        returnUrl: $overrides['returnUrl'] ?? 'https://app.test/invoices/1042',
        // `??` would read an explicit null as absent, which is exactly the
        // case this helper needs to be able to express.
        reference: array_key_exists('reference', $overrides) ? $overrides['reference'] : 'INV-1042',
        currency: $overrides['currency'] ?? 'MYR',
    );
}

// ── The primitive ─────────────────────────────────────────────────────────

it('charges an arbitrary amount with no plan and no subscription', function () {
    Http::fake(['*/api/v3/bills' => Http::response(['id' => 'bill_9', 'url' => 'https://billplz.test/bills/bill_9'])]);

    $gateway = new BillplzGateway([
        'api_key' => 'key', 'x_signature_key' => 'xsig', 'collection_id' => 'col1',
        'callback_url' => 'https://app.test/webhooks/billplz', 'sandbox' => true,
    ]);

    $intent = $gateway->checkout(chargeRequest());

    expect($intent->redirectUrl)->toBe('https://billplz.test/bills/bill_9')
        ->and($intent->externalId)->toBe('bill_9');

    Http::assertSent(fn ($request): bool => $request['amount'] === 12500
        && $request['description'] === 'Invoice INV-1042'
        && $request['email'] === 'ali@example.test'
        // The caller's own id, echoed back — an application charging invoice
        // #1042 needs the gateway to return something it can find #1042 from.
        && $request['reference_1'] === 'INV-1042');
});

it('mints a reference only when the caller supplied none', function () {
    Http::fake(['*/api/createBill' => Http::response([['BillCode' => 'abc123']])]);

    $gateway = new ToyyibPayGateway([
        'secret_key' => 's', 'category_code' => 'c',
        'callback_url' => 'https://app.test/cb', 'sandbox' => true,
    ]);

    $gateway->checkout(chargeRequest(['reference' => null]));

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'createBill')
        && str_starts_with((string) $request['billExternalReferenceNo'], 'SUB'));

    $gateway->checkout(chargeRequest());

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'createBill')
        && $request['billExternalReferenceNo'] === 'INV-1042');
});

it('lets the caller override the configured callback url', function () {
    Http::fake(['*/api/v3/bills' => Http::response(['id' => 'b', 'url' => 'https://billplz.test/b'])]);

    $gateway = new BillplzGateway([
        'api_key' => 'key', 'x_signature_key' => 'xsig', 'collection_id' => 'col1',
        'callback_url' => 'https://app.test/webhooks/billplz', 'sandbox' => true,
    ]);

    // A host application routing webhooks per tenant cannot express that in a
    // single config value.
    $gateway->checkout(new CheckoutRequest(
        amountCents: 100,
        description: 'x',
        customerName: 'n',
        customerEmail: 'e@x.test',
        returnUrl: 'https://app.test/r',
        callbackUrl: 'https://tenant.app.test/hooks',
    ));

    Http::assertSent(fn ($request): bool => $request['callback_url'] === 'https://tenant.app.test/hooks');
});

// ── A plan checkout still sends exactly what it used to ───────────────────

it('sends the same payload for a plan checkout as before the refactor', function () {
    Http::fake(['*/api/v3/bills' => Http::response(['id' => 'bill_1', 'url' => 'https://billplz.test/bills/bill_1'])]);

    $gateway = new BillplzGateway([
        'api_key' => 'key', 'x_signature_key' => 'xsig', 'collection_id' => 'col1',
        'callback_url' => 'https://app.test/webhooks/billplz', 'sandbox' => true,
    ]);

    $user = User::create(['name' => 'Ali', 'email' => 'ali@example.test']);
    $plan = app(PlanRepository::class)->find('pro');

    $gateway->createCheckout($user, $plan, PlanInterval::Monthly, 'https://app.test/done');

    // The mapping lives in one trait now rather than in ten drivers, and this
    // is what says the move was lossless.
    Http::assertSent(fn ($request): bool => $request['amount'] === 4900
        && $request['description'] === 'Pro (monthly)'
        && $request['name'] === 'Ali'
        && $request['email'] === 'ali@example.test'
        && $request['redirect_url'] === 'https://app.test/done'
        && $request['callback_url'] === 'https://app.test/webhooks/billplz');
});

// ── Stripe keeps its two paths apart ──────────────────────────────────────

it('uses payment mode with ad-hoc pricing for a one-off Stripe charge', function () {
    Http::fake(['*/v1/checkout/sessions' => Http::response(['id' => 'cs_1', 'url' => 'https://stripe.test/cs_1'])]);

    $gateway = new StripeGateway(['secret' => 'sk_test', 'webhook_secret' => 'whsec']);

    $gateway->checkout(chargeRequest(['currency' => 'USD']));

    // A subscription renews and fires invoice.paid forever; an order with
    // price_data happens once. Collapsing them would mean either inventing a
    // Stripe price per invoice or turning a one-time fee into a recurring one.
    Http::assertSent(fn ($request): bool => $request['mode'] === 'payment'
        && $request['line_items'][0]['price_data']['currency'] === 'usd'
        && $request['line_items'][0]['price_data']['unit_amount'] === 12500
        && $request['client_reference_id'] === 'INV-1042');
});

it('keeps subscription mode for a Stripe plan checkout', function () {
    Http::fake(['*/v1/checkout/sessions' => Http::response(['id' => 'cs_2', 'url' => 'https://stripe.test/cs_2'])]);

    $gateway = new StripeGateway([
        'secret' => 'sk_test', 'webhook_secret' => 'whsec',
        'prices' => ['pro' => ['monthly' => 'price_123']],
    ]);

    $user = User::create(['name' => 'Ali', 'email' => 'ali@example.test']);
    $plan = app(PlanRepository::class)->find('pro');

    $gateway->createCheckout($user, $plan, PlanInterval::Monthly, 'https://app.test/done');

    Http::assertSent(fn ($request): bool => $request['mode'] === 'subscription'
        && $request['line_items'][0]['price'] === 'price_123');
});

// ── Asking instead of waiting ─────────────────────────────────────────────

it('reads a bill back from Billplz', function () {
    Http::fake(['*/api/v3/bills/bill_9' => Http::response([
        'id' => 'bill_9', 'paid' => true, 'state' => 'paid', 'amount' => 12500,
    ])]);

    $gateway = new BillplzGateway(['api_key' => 'key', 'sandbox' => true]);

    $status = $gateway->fetch('bill_9');

    // A webhook that was never delivered is otherwise indistinguishable from
    // a payment that never happened — which is the state a customer is in
    // when they say "I paid" and the application says otherwise.
    expect($status?->paid)->toBeTrue()
        ->and($status?->status)->toBe('paid')
        ->and($status?->amountCents)->toBe(12500);
});

it('returns null for a bill the gateway has no record of', function () {
    Http::fake(['*/api/v3/bills/*' => Http::response(['error' => 'not found'], 404)]);

    expect((new BillplzGateway(['api_key' => 'key', 'sandbox' => true]))->fetch('nope'))->toBeNull();
});

it('reads Stripe payment_status, not session status', function () {
    Http::fake(['*/v1/checkout/sessions/cs_1' => Http::response([
        'id' => 'cs_1', 'status' => 'complete', 'payment_status' => 'unpaid',
        'amount_total' => 12500, 'currency' => 'myr',
    ])]);

    $status = (new StripeGateway(['secret' => 'sk_test']))->fetch('cs_1');

    // A session can be `complete` with `payment_status: unpaid` when the
    // customer chose a delayed method. Reading `status` alone reports that
    // as paid.
    expect($status?->paid)->toBeFalse()
        ->and($status?->status)->toBe('unpaid')
        ->and($status?->currency)->toBe('MYR');
});

it('throws rather than answering null when a gateway cannot be asked', function () {
    // Null already means "asked, and there is nothing there". A caller must be
    // able to tell that from "this gateway has no way to be asked", since only
    // the first is an answer.
    expect(fn () => (new LocalGateway)->fetch('anything'))
        ->toThrow(UnsupportedByGateway::class);

    // senangPay is a signed redirect form with no query API at all — there is
    // nothing to ask, and inventing an endpoint would be worse than saying so.
    expect(fn () => (new SenangPayGateway(['secret_key' => 's']))->fetch('anything'))
        ->toThrow(UnsupportedByGateway::class);
});
