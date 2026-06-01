<?php

use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
use CleaniqueCoders\LaravelBilling\Gateways\Ipay88Gateway;
use CleaniqueCoders\LaravelBilling\Gateways\Support\RedirectForm;
use CleaniqueCoders\LaravelBilling\Services\PlanRepository;
use CleaniqueCoders\LaravelBilling\Tests\Fixtures\Models\User;
use Illuminate\Http\Request;

function ipay88Gateway(): Ipay88Gateway
{
    return new Ipay88Gateway([
        'merchant_code' => 'MERCH1',
        'merchant_key' => 'secretkey',
        'payment_id' => '2',
        'backend_url' => 'https://app.test/webhooks/ipay88',
    ]);
}

/** Reproduce the verified iPay88 signature scheme for assertions. */
function ipay88Sig(string $tail): string
{
    return base64_encode(sha1('secretkey'.'MERCH1'.$tail, true));
}

beforeEach(function () {
    config()->set('billing.store', 'config');
    config()->set('billing.currency', 'MYR');
    config()->set('billing.plans', [
        'pro' => ['name' => 'Pro', 'price_cents' => ['monthly' => 4900, 'annual' => 49000], 'limits' => []],
    ]);
});

it('builds a signed redirect-bridge checkout', function () {
    $user = User::create(['name' => 'Ali', 'email' => 'ali@example.test']);
    $plan = app(PlanRepository::class)->find('pro');

    $intent = ipay88Gateway()->createCheckout($user, $plan, PlanInterval::Monthly, 'https://app.test/done');

    expect($intent->redirectUrl)->toContain('billing/redirect');

    $token = (string) str($intent->redirectUrl)->after('token=');
    $payload = RedirectForm::verify(urldecode($token));

    expect($payload)->not->toBeNull()
        ->and($payload['action'])->toBe('https://www.mobile88.com/epayment/entry.asp')
        ->and($payload['fields']['RefNo'])->toBe($intent->externalId)
        ->and($payload['fields']['Amount'])->toBe('49.00')
        // signature over MerchantKey.MerchantCode.RefNo.Amount(stripped).Currency
        ->and($payload['fields']['Signature'])->toBe(ipay88Sig($intent->externalId.'4900'.'MYR'));
});

it('activates on a valid Status=1 backend callback', function () {
    $fields = ['PaymentId' => '2', 'RefNo' => 'SUBX', 'Amount' => '49.00', 'Currency' => 'MYR', 'Status' => '1'];
    $fields['Signature'] = ipay88Sig('2'.'SUBX'.'4900'.'MYR'.'1');

    $event = ipay88Gateway()->parseWebhook(Request::create('/cb', 'POST', $fields));

    expect($event->type)->toBe(WebhookEventType::SubscriptionActivated)
        ->and($event->externalId)->toBe('SUBX')
        ->and($event->amountCents)->toBe(4900);
});

it('maps a failed status to PaymentFailed', function () {
    $fields = ['PaymentId' => '2', 'RefNo' => 'SUBX', 'Amount' => '49.00', 'Currency' => 'MYR', 'Status' => '0'];
    $fields['Signature'] = ipay88Sig('2'.'SUBX'.'4900'.'MYR'.'0');

    expect(ipay88Gateway()->parseWebhook(Request::create('/cb', 'POST', $fields))->type)
        ->toBe(WebhookEventType::PaymentFailed);
});

it('rejects a callback with a bad signature', function () {
    $fields = ['PaymentId' => '2', 'RefNo' => 'SUBX', 'Amount' => '49.00', 'Currency' => 'MYR', 'Status' => '1', 'Signature' => 'wrong'];

    expect(ipay88Gateway()->parseWebhook(Request::create('/cb', 'POST', $fields)))->toBeNull();
});
