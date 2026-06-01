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
 * eGHL driver — a form-POST gateway. The browser POSTs hashed fields to the eGHL
 * payment page, so createCheckout returns the billing.gateway.redirect bridge URL.
 *
 * Config: service_id, password, payment_url (from your eGHL onboarding —
 * sandbox vs production differ), callback_url, hash_algo (sha256|sha512).
 *
 * HashValue (SHA256/512):
 *  - request:  hash(password . service_id . payment_id . return_url . amount . currency)
 *  - response: hash(password . txn_id . service_id . payment_id . txn_status . amount . currency . auth_code)
 * TxnStatus == 0 means success.
 *
 * NOTE: eGHL varies the exact HashValue field order across products/versions.
 * Confirm the concatenation and payment_url against your eGHL integration guide
 * and adjust requestHash()/responseHash() if your merchant profile differs.
 */
class EghlGateway extends Gateway
{
    public function createCheckout(Billable $billable, Plan $plan, PlanInterval $interval, string $returnUrl): CheckoutIntent
    {
        $paymentId = 'SUB'.Str::upper(Str::random(12));
        $amount = number_format($plan->priceCents($interval) / 100, 2, '.', '');
        $currency = (string) config('billing.currency', 'MYR');

        $fields = [
            'TransactionType' => 'SALE',
            'PymtMethod' => 'ANY',
            'ServiceID' => (string) $this->config('service_id'),
            'PaymentID' => $paymentId,
            'OrderNumber' => $paymentId,
            'PaymentDesc' => $plan->name.' ('.$interval->value.')',
            'MerchantReturnURL' => $returnUrl,
            'MerchantCallBackURL' => (string) $this->config('callback_url'),
            'Amount' => $amount,
            'CurrencyCode' => $currency,
            'CustName' => $billable->billingName(),
            'CustEmail' => $billable->billingEmail(),
            'CustPhone' => '',
            'HashValue' => $this->requestHash($paymentId, $returnUrl, $amount, $currency),
        ];

        $token = RedirectForm::sign((string) $this->config('payment_url'), $fields);

        return new CheckoutIntent(route('billing.gateway.redirect', ['token' => $token]), $paymentId);
    }

    public function cancel(Subscription $subscription): void
    {
        // One-time by default — nothing to cancel (delete the card token if vaulted).
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $paymentId = (string) $request->input('PaymentID');
        $amount = (string) $request->input('Amount');
        $currency = (string) $request->input('CurrencyCode');
        $txnStatus = (string) $request->input('TxnStatus');
        $txnId = (string) ($request->input('TransactionID') ?? $request->input('TxnID') ?? '');
        $authCode = (string) $request->input('AuthCode');

        $expected = $this->responseHash($txnId, $paymentId, $txnStatus, $amount, $currency, $authCode);

        if (! $this->signaturesMatch($expected, (string) $request->input('HashValue'))) {
            return null;
        }

        $providerEventId = 'eghl-'.$txnId;

        if ($txnStatus !== '0') {
            return new WebhookEvent(
                type: WebhookEventType::PaymentFailed,
                externalId: $paymentId,
                providerEventId: $providerEventId,
                rawPayload: $request->all(),
            );
        }

        return new WebhookEvent(
            type: WebhookEventType::SubscriptionActivated,
            externalId: $paymentId,
            amountCents: (int) round(((float) $amount) * 100),
            providerEventId: $providerEventId,
            rawPayload: $request->all(),
        );
    }

    protected function requestHash(string $paymentId, string $returnUrl, string $amount, string $currency): string
    {
        return $this->hashWith(
            ((string) $this->config('password')).((string) $this->config('service_id'))
            .$paymentId.$returnUrl.$amount.$currency
        );
    }

    protected function responseHash(string $txnId, string $paymentId, string $txnStatus, string $amount, string $currency, string $authCode): string
    {
        return $this->hashWith(
            ((string) $this->config('password')).$txnId.((string) $this->config('service_id'))
            .$paymentId.$txnStatus.$amount.$currency.$authCode
        );
    }

    protected function hashWith(string $data): string
    {
        return $this->config('hash_algo') === 'sha512'
            ? $this->sha512($data)
            : $this->sha256($data);
    }
}
