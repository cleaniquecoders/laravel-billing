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
 * SecurePay driver — the browser POSTs signed fields to /api/v1/payments, so
 * createCheckout returns the billing.gateway.redirect bridge URL.
 *
 * Config: uid, auth_token, checksum_token, callback_url, sandbox (bool).
 *
 * Checksums are HMAC-SHA256 with the checksum token, over values joined by "|":
 *  - request:  the 9 fields in alphabetical key order (uid included; token/checksum excluded).
 *  - response: every returned field except checksum, sorted by key, values joined by "|".
 * payment_status == "true" means success.
 */
class SecurePayGateway extends Gateway
{
    /** Request checksum field set, alphabetical (per SecurePay docs). */
    protected array $requestKeys = [
        'buyer_email', 'buyer_name', 'buyer_phone', 'callback_url', 'order_number',
        'product_description', 'redirect_url', 'transaction_amount', 'uid',
    ];

    public function createCheckout(Billable $billable, Plan $plan, PlanInterval $interval, string $returnUrl): CheckoutIntent
    {
        $orderId = 'SUB'.Str::upper(Str::random(12));
        $amount = number_format($plan->priceCents($interval) / 100, 2, '.', '');

        $fields = [
            'uid' => (string) $this->config('uid'),
            'token' => (string) $this->config('auth_token'),
            'order_number' => $orderId,
            'buyer_name' => $billable->billingName(),
            'buyer_email' => $billable->billingEmail(),
            'buyer_phone' => (string) $this->config('default_phone', '0000000000'),
            'transaction_amount' => $amount,
            'product_description' => $plan->name.' ('.$interval->value.')',
            'callback_url' => (string) $this->config('callback_url'),
            'redirect_url' => $returnUrl,
        ];
        $fields['checksum'] = $this->requestChecksum($fields);

        $token = RedirectForm::sign($this->base().'/api/v1/payments', $fields);

        return new CheckoutIntent(route('billing.gateway.redirect', ['token' => $token]), $orderId);
    }

    public function cancel(Subscription $subscription): void
    {
        // One-time by default — nothing to cancel upstream.
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $source = collect($request->except('checksum'))->sortKeys()->values()->implode('|');
        $expected = $this->hmac('sha256', $source, (string) $this->config('checksum_token'));

        if (! $this->signaturesMatch($expected, (string) $request->input('checksum'))) {
            return null;
        }

        $orderId = (string) $request->input('order_number');
        $providerEventId = 'securepay-'.((string) $request->input('payment_id', $orderId));
        $paid = in_array(strtolower((string) $request->input('payment_status')), ['true', '1', 'success'], true);

        if (! $paid) {
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
            amountCents: (int) round(((float) $request->input('transaction_amount', 0)) * 100),
            providerEventId: $providerEventId,
            rawPayload: $request->all(),
        );
    }

    /**
     * @param  array<string,string>  $fields
     */
    protected function requestChecksum(array $fields): string
    {
        $source = collect($this->requestKeys)->map(fn ($key) => $fields[$key] ?? '')->implode('|');

        return $this->hmac('sha256', $source, (string) $this->config('checksum_token'));
    }

    protected function base(): string
    {
        return $this->config('sandbox')
            ? 'https://sandbox.securepay.my'
            : 'https://securepay.my';
    }
}
