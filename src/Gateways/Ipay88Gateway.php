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
 * iPay88 driver — a one-time, form-POST gateway (no native subscriptions). The
 * customer's browser POSTs signed fields to entry.asp, so createCheckout returns
 * the billing.gateway.redirect bridge URL (auto-submitting form).
 *
 * Config: merchant_code, merchant_key, payment_id (optional), backend_url
 * (your server callback / BackendURL — must be publicly reachable).
 *
 * Signature: base64(SHA1(MerchantKey . MerchantCode . RefNo . Amount . Currency))
 * with Amount stripped of separators; the response form prepends PaymentId and
 * appends Status.
 */
class Ipay88Gateway extends Gateway
{
    protected string $entryUrl = 'https://www.mobile88.com/epayment/entry.asp';

    public function createCheckout(Billable $billable, Plan $plan, PlanInterval $interval, string $returnUrl): CheckoutIntent
    {
        $refNo = 'SUB'.Str::upper(Str::random(12));
        $amount = number_format($plan->priceCents($interval) / 100, 2, '.', '');
        $currency = (string) config('billing.currency', 'MYR');

        $fields = [
            'MerchantCode' => (string) $this->config('merchant_code'),
            'PaymentId' => (string) $this->config('payment_id', ''),
            'RefNo' => $refNo,
            'Amount' => $amount,
            'Currency' => $currency,
            'ProdDesc' => $plan->name.' ('.$interval->value.')',
            'UserName' => $billable->billingName(),
            'UserEmail' => $billable->billingEmail(),
            'UserContact' => '',
            'Remark' => '',
            'Lang' => 'UTF-8',
            'ResponseURL' => $returnUrl,
            'BackendURL' => (string) $this->config('backend_url'),
            'Signature' => $this->requestSignature($refNo, $amount, $currency),
        ];

        $token = RedirectForm::sign($this->entryUrl, $fields);

        return new CheckoutIntent(route('billing.gateway.redirect', ['token' => $token]), $refNo);
    }

    public function cancel(Subscription $subscription): void
    {
        // One-time gateway — nothing to cancel upstream.
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $refNo = (string) $request->input('RefNo');
        $amount = (string) $request->input('Amount');
        $currency = (string) $request->input('Currency');
        $status = (string) $request->input('Status');
        $paymentId = (string) $request->input('PaymentId');

        $expected = $this->responseSignature($paymentId, $refNo, $amount, $currency, $status);

        if (! $this->signaturesMatch($expected, (string) $request->input('Signature'))) {
            return null;
        }

        $providerEventId = 'ipay88-'.$request->input('TransId', $refNo);

        if ($status !== '1') {
            return new WebhookEvent(
                type: WebhookEventType::PaymentFailed,
                externalId: $refNo,
                providerEventId: $providerEventId,
                rawPayload: $request->all(),
            );
        }

        return new WebhookEvent(
            type: WebhookEventType::SubscriptionActivated,
            externalId: $refNo,
            amountCents: (int) round(((float) $amount) * 100),
            providerEventId: $providerEventId,
            rawPayload: $request->all(),
        );
    }

    protected function requestSignature(string $refNo, string $amount, string $currency): string
    {
        return $this->sha1Base64(
            ((string) $this->config('merchant_key')).((string) $this->config('merchant_code'))
            .$refNo.$this->stripAmount($amount).$currency
        );
    }

    protected function responseSignature(string $paymentId, string $refNo, string $amount, string $currency, string $status): string
    {
        return $this->sha1Base64(
            ((string) $this->config('merchant_key')).((string) $this->config('merchant_code'))
            .$paymentId.$refNo.$this->stripAmount($amount).$currency.$status
        );
    }

    /** iPay88 strips commas and dots from Amount before signing. */
    protected function stripAmount(string $amount): string
    {
        return str_replace([',', '.'], '', $amount);
    }
}
