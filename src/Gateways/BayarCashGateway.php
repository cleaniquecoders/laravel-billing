<?php

namespace CleaniqueCoders\LaravelBilling\Gateways;

use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutIntent;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutRequest;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\WebhookEvent;
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
use CleaniqueCoders\LaravelBilling\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * BayarCash driver — FPX, hosted payment page (Bayarcash API v3 over Laravel's
 * HTTP client). Requests authenticate with the Personal Access Token (PAT);
 * callbacks are verified with an HMAC-SHA256 checksum keyed by the API Secret Key.
 *
 * Config: pat, portal_key, api_secret_key, callback_url, api_url
 * (default https://api.console.bayar.cash/v3 — use your sandbox base for testing),
 * payment_channel (default 1 = FPX).
 *
 * Status: 0 new · 1 pending · 2 failed · 3 success · 4 cancelled.
 *
 * NOTE: confirm the callback checksum field order and api_url against the
 * Bayarcash v3 docs / official PHP SDK; adjust $checksumKeys if your portal
 * differs.
 */
class BayarCashGateway extends Gateway
{
    /** Callback fields whose values form the checksum source (| joined). */
    protected array $checksumKeys = [
        'transaction_id', 'exchange_reference_number', 'order_number',
        'currency', 'amount', 'payer_name', 'payer_email', 'status',
    ];

    public function checkout(CheckoutRequest $request): CheckoutIntent
    {
        $orderId = $this->reference($request);
        $amount = $request->amountDecimal();

        $res = Http::withToken((string) $this->config('pat'))
            ->acceptJson()
            ->post($this->base().'/payment-intents', [
                'payment_channel' => (int) $this->config('payment_channel', 1),
                'portal_key' => (string) $this->config('portal_key'),
                'order_number' => $orderId,
                'amount' => $amount,
                'payer_name' => $request->customerName,
                'payer_email' => $request->customerEmail,
                'payer_telephone_number' => $request->customerPhone ?? (string) $this->config('default_phone', '0000000000'),
                'return_url' => $request->returnUrl,
                'callback_url' => $this->callbackUrl($request),
            ])->throw()->json();

        $url = $res['url'] ?? data_get($res, 'data.url');

        if (! is_string($url) || $url === '') {
            throw new RuntimeException('Bayarcash did not return a payment URL.');
        }

        return new CheckoutIntent($url, $orderId);
    }

    public function cancel(Subscription $subscription): void
    {
        // FPX Direct Debit mandate cancellation would go here when enrolled;
        // a one-off FPX payment has nothing to cancel.
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $expected = $this->hmac('sha256', $this->checksumSource($request->all()), (string) $this->config('api_secret_key'));

        if (! $this->signaturesMatch($expected, (string) $request->input('checksum'))) {
            return null;
        }

        $orderId = (string) $request->input('order_number');
        $status = (string) $request->input('status');
        $providerEventId = 'bayarcash-'.((string) $request->input('transaction_id', $orderId));

        return match ($status) {
            '3' => new WebhookEvent(
                type: WebhookEventType::SubscriptionActivated,
                externalId: $orderId,
                amountCents: (int) round(((float) $request->input('amount', 0)) * 100),
                providerEventId: $providerEventId,
                rawPayload: $request->all(),
            ),
            '4' => new WebhookEvent(
                type: WebhookEventType::SubscriptionCanceled,
                externalId: $orderId,
                providerEventId: $providerEventId,
                rawPayload: $request->all(),
            ),
            '2' => new WebhookEvent(
                type: WebhookEventType::PaymentFailed,
                externalId: $orderId,
                providerEventId: $providerEventId,
                rawPayload: $request->all(),
            ),
            default => null, // 0 new / 1 pending — ignore
        };
    }

    /**
     * @param  array<string,mixed>  $data
     */
    protected function checksumSource(array $data): string
    {
        return collect($this->checksumKeys)
            ->map(fn ($key) => (string) ($data[$key] ?? ''))
            ->implode('|');
    }

    protected function base(): string
    {
        return (string) $this->config('api_url', 'https://api.console.bayar.cash/v3');
    }
}
