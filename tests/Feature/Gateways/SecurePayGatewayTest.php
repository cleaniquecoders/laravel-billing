<?php

use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
use CleaniqueCoders\LaravelBilling\Gateways\SecurePayGateway;
use CleaniqueCoders\LaravelBilling\Gateways\Support\RedirectForm;
use CleaniqueCoders\LaravelBilling\Services\PlanRepository;
use CleaniqueCoders\LaravelBilling\Tests\Fixtures\Models\User;
use Illuminate\Http\Request;

function securePayGateway(): SecurePayGateway
{
    return new SecurePayGateway([
        'uid' => 'UID1', 'auth_token' => 'authtok', 'checksum_token' => 'csum',
        'callback_url' => 'https://app.test/webhooks/securepay', 'sandbox' => true,
        'default_phone' => '0123456789',
    ]);
}

/** Build a callback with a valid checksum: all fields except checksum, sorted by key, values | joined. */
function securePayCallback(array $fields, string $token = 'csum'): Request
{
    ksort($fields);
    $fields['checksum'] = hash_hmac('sha256', implode('|', array_values($fields)), $token);

    return Request::create('/cb', 'POST', $fields);
}

beforeEach(function () {
    config()->set('billing.store', 'config');
    config()->set('billing.plans', [
        'pro' => ['name' => 'Pro', 'price_cents' => ['monthly' => 4900, 'annual' => 49000], 'limits' => []],
    ]);
});

it('builds a signed redirect-bridge checkout with the request checksum', function () {
    $user = User::create(['name' => 'Ali', 'email' => 'ali@example.test']);
    $plan = app(PlanRepository::class)->find('pro');

    $intent = securePayGateway()->createCheckout($user, $plan, PlanInterval::Monthly, 'https://app.test/done');

    $payload = RedirectForm::verify(urldecode((string) str($intent->redirectUrl)->after('token=')));
    $f = $payload['fields'];

    // request checksum: values of the 9 fields in alphabetical key order, | joined.
    $source = implode('|', [
        $f['buyer_email'], $f['buyer_name'], $f['buyer_phone'], $f['callback_url'], $f['order_number'],
        $f['product_description'], $f['redirect_url'], $f['transaction_amount'], $f['uid'],
    ]);

    expect($payload['action'])->toBe('https://sandbox.securepay.my/api/v1/payments')
        ->and($f['order_number'])->toBe($intent->externalId)
        ->and($f['checksum'])->toBe(hash_hmac('sha256', $source, 'csum'));
});

it('activates on a paid callback with a valid checksum', function () {
    $request = securePayCallback([
        'order_number' => 'SUBX', 'payment_status' => 'true', 'transaction_amount' => '49.00',
        'currency' => 'MYR', 'payment_id' => 'PID1',
    ]);

    $event = securePayGateway()->parseWebhook($request);

    expect($event->type)->toBe(WebhookEventType::SubscriptionActivated)
        ->and($event->externalId)->toBe('SUBX')
        ->and($event->amountCents)->toBe(4900);
});

it('maps a non-paid status to PaymentFailed', function () {
    $request = securePayCallback(['order_number' => 'SUBX', 'payment_status' => 'false', 'transaction_amount' => '49.00']);

    expect(securePayGateway()->parseWebhook($request)->type)->toBe(WebhookEventType::PaymentFailed);
});

it('rejects a callback with an invalid checksum', function () {
    $request = securePayCallback(['order_number' => 'SUBX', 'payment_status' => 'true'], 'WRONG');

    expect(securePayGateway()->parseWebhook($request))->toBeNull();
});
