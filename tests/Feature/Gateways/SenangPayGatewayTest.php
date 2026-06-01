<?php

use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
use CleaniqueCoders\LaravelBilling\Gateways\SenangPayGateway;
use CleaniqueCoders\LaravelBilling\Gateways\Support\RedirectForm;
use CleaniqueCoders\LaravelBilling\Services\PlanRepository;
use CleaniqueCoders\LaravelBilling\Tests\Fixtures\Models\User;
use Illuminate\Http\Request;

function senangPayGateway(): SenangPayGateway
{
    return new SenangPayGateway(['merchant_id' => 'M123', 'secret_key' => 'sekret', 'sandbox' => true]);
}

beforeEach(function () {
    config()->set('billing.store', 'config');
    config()->set('billing.plans', [
        'pro' => ['name' => 'Pro', 'price_cents' => ['monthly' => 4900, 'annual' => 49000], 'limits' => []],
    ]);
});

it('builds a signed redirect-bridge checkout with the MD5 request hash', function () {
    $user = User::create(['name' => 'Ali', 'email' => 'ali@example.test']);
    $plan = app(PlanRepository::class)->find('pro');

    $intent = senangPayGateway()->createCheckout($user, $plan, PlanInterval::Monthly, 'https://app.test/done');

    $payload = RedirectForm::verify(urldecode((string) str($intent->redirectUrl)->after('token=')));

    expect($payload['action'])->toBe('https://sandbox.senangpay.my/payment/M123')
        ->and($payload['fields']['order_id'])->toBe($intent->externalId)
        ->and($payload['fields']['amount'])->toBe('49.00')
        ->and($payload['fields']['hash'])->toBe(md5('sekret'.'Pro (monthly)'.'49.00'.$intent->externalId));
});

it('activates on a status_id=1 callback with a valid hash', function () {
    $fields = ['status_id' => '1', 'order_id' => 'SUBX', 'transaction_id' => 'T1', 'msg' => 'ok'];
    $fields['hash'] = md5('sekret'.'1'.'SUBX'.'T1'.'ok');

    $event = senangPayGateway()->parseWebhook(Request::create('/cb', 'POST', $fields));

    expect($event->type)->toBe(WebhookEventType::SubscriptionActivated)
        ->and($event->externalId)->toBe('SUBX');
});

it('maps a non-success status to PaymentFailed', function () {
    $fields = ['status_id' => '0', 'order_id' => 'SUBX', 'transaction_id' => 'T1', 'msg' => 'fail'];
    $fields['hash'] = md5('sekret'.'0'.'SUBX'.'T1'.'fail');

    expect(senangPayGateway()->parseWebhook(Request::create('/cb', 'POST', $fields))->type)
        ->toBe(WebhookEventType::PaymentFailed);
});

it('rejects a callback with a tampered hash', function () {
    $fields = ['status_id' => '1', 'order_id' => 'SUBX', 'transaction_id' => 'T1', 'msg' => 'ok', 'hash' => 'nope'];

    expect(senangPayGateway()->parseWebhook(Request::create('/cb', 'POST', $fields)))->toBeNull();
});
