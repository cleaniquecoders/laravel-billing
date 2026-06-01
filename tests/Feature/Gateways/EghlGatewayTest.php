<?php

use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
use CleaniqueCoders\LaravelBilling\Gateways\EghlGateway;
use CleaniqueCoders\LaravelBilling\Gateways\Support\RedirectForm;
use CleaniqueCoders\LaravelBilling\Services\PlanRepository;
use CleaniqueCoders\LaravelBilling\Tests\Fixtures\Models\User;
use Illuminate\Http\Request;

function eghlGateway(): EghlGateway
{
    return new EghlGateway([
        'service_id' => 'SVC1', 'password' => 'pwd',
        'payment_url' => 'https://test2pay.ghl.com/IPGSG/Payment.aspx',
        'callback_url' => 'https://app.test/webhooks/eghl', 'hash_algo' => 'sha256',
    ]);
}

beforeEach(function () {
    config()->set('billing.store', 'config');
    config()->set('billing.currency', 'MYR');
    config()->set('billing.plans', [
        'pro' => ['name' => 'Pro', 'price_cents' => ['monthly' => 4900, 'annual' => 49000], 'limits' => []],
    ]);
});

it('builds a signed redirect-bridge checkout with the request HashValue', function () {
    $user = User::create(['name' => 'Ali', 'email' => 'ali@example.test']);
    $plan = app(PlanRepository::class)->find('pro');

    $intent = eghlGateway()->createCheckout($user, $plan, PlanInterval::Monthly, 'https://app.test/done');

    $payload = RedirectForm::verify(urldecode((string) str($intent->redirectUrl)->after('token=')));

    $expected = hash('sha256', 'pwd'.'SVC1'.$intent->externalId.'https://app.test/done'.'49.00'.'MYR');

    expect($payload['action'])->toBe('https://test2pay.ghl.com/IPGSG/Payment.aspx')
        ->and($payload['fields']['PaymentID'])->toBe($intent->externalId)
        ->and($payload['fields']['HashValue'])->toBe($expected);
});

it('activates on a TxnStatus=0 callback with a valid hash', function () {
    $fields = ['PaymentID' => 'SUBX', 'Amount' => '49.00', 'CurrencyCode' => 'MYR', 'TxnStatus' => '0', 'TransactionID' => 'TX1', 'AuthCode' => 'A1'];
    $fields['HashValue'] = hash('sha256', 'pwd'.'TX1'.'SVC1'.'SUBX'.'0'.'49.00'.'MYR'.'A1');

    $event = eghlGateway()->parseWebhook(Request::create('/cb', 'POST', $fields));

    expect($event->type)->toBe(WebhookEventType::SubscriptionActivated)
        ->and($event->externalId)->toBe('SUBX')
        ->and($event->amountCents)->toBe(4900);
});

it('maps a non-zero TxnStatus to PaymentFailed', function () {
    $fields = ['PaymentID' => 'SUBX', 'Amount' => '49.00', 'CurrencyCode' => 'MYR', 'TxnStatus' => '1', 'TransactionID' => 'TX1', 'AuthCode' => ''];
    $fields['HashValue'] = hash('sha256', 'pwd'.'TX1'.'SVC1'.'SUBX'.'1'.'49.00'.'MYR'.'');

    expect(eghlGateway()->parseWebhook(Request::create('/cb', 'POST', $fields))->type)
        ->toBe(WebhookEventType::PaymentFailed);
});

it('rejects a callback with a tampered HashValue', function () {
    $fields = ['PaymentID' => 'SUBX', 'Amount' => '49.00', 'CurrencyCode' => 'MYR', 'TxnStatus' => '0', 'TransactionID' => 'TX1', 'AuthCode' => 'A1', 'HashValue' => 'nope'];

    expect(eghlGateway()->parseWebhook(Request::create('/cb', 'POST', $fields)))->toBeNull();
});
