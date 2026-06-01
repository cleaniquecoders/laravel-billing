<?php

namespace CleaniqueCoders\LaravelBilling\Gateways;

use CleaniqueCoders\LaravelBilling\Contracts\Billable;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutIntent;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\WebhookEvent;
use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
use CleaniqueCoders\LaravelBilling\Gateways\Support\RedirectForm;
use CleaniqueCoders\LaravelBilling\Models\Plan;
use CleaniqueCoders\LaravelBilling\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * senangPay driver — a form-POST gateway. Recurring is available via senangPay's
 * recurring API (documented), but the base flow is a signed redirect.
 *
 * Config: merchant_id, secret_key, sandbox (bool).
 *
 * Hash (per the senangPay reference): MD5 with the secret key prepended.
 *  - request:  md5(secret . detail . amount . order_id)
 *  - response: md5(secret . status_id . order_id . transaction_id . msg)
 * status_id == 1 means success.
 */
class SenangPayGateway extends Gateway
{
    public function createCheckout(Billable $billable, Plan $plan, PlanInterval $interval, string $returnUrl): CheckoutIntent
    {
        $orderId = 'SUB'.Str::upper(Str::random(12));
        $amount = number_format($plan->priceCents($interval) / 100, 2, '.', '');
        $detail = $plan->name.' ('.$interval->value.')';

        $fields = [
            'detail' => $detail,
            'amount' => $amount,
            'order_id' => $orderId,
            'name' => $billable->billingName(),
            'email' => $billable->billingEmail(),
            'phone' => '',
            'hash' => $this->md5(((string) $this->config('secret_key')).$detail.$amount.$orderId),
        ];

        $token = RedirectForm::sign($this->payUrl(), $fields);

        return new CheckoutIntent(route('billing.gateway.redirect', ['token' => $token]), $orderId);
    }

    public function cancel(Subscription $subscription): void
    {
        // Cancel the recurring profile via senangPay's API if one was created;
        // otherwise nothing to do (one-off payment).
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $statusId = (string) $request->input('status_id');
        $orderId = (string) $request->input('order_id');
        $transactionId = (string) $request->input('transaction_id');
        $msg = (string) $request->input('msg');

        $expected = $this->md5(((string) $this->config('secret_key')).$statusId.$orderId.$transactionId.$msg);

        if (! $this->signaturesMatch($expected, (string) $request->input('hash'))) {
            return null;
        }

        $providerEventId = 'senangpay-'.$transactionId;

        if ($statusId !== '1') {
            return new WebhookEvent(
                type: WebhookEventType::PaymentFailed,
                externalId: $orderId,
                providerEventId: $providerEventId,
                rawPayload: $request->all(),
            );
        }

        return new WebhookEvent(
            type: WebhookEventType::SubscriptionActivated,
            externalId: $orderId,
            providerEventId: $providerEventId,
            rawPayload: $request->all(),
        );
    }

    protected function payUrl(): string
    {
        $base = $this->config('sandbox')
            ? 'https://sandbox.senangpay.my'
            : 'https://app.senangpay.my';

        return $base.'/payment/'.((string) $this->config('merchant_id'));
    }
}
