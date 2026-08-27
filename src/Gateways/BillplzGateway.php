<?php

namespace CleaniqueCoders\LaravelBilling\Gateways;

use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutIntent;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutRequest;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\PaymentStatus;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\WebhookEvent;
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
use CleaniqueCoders\LaravelBilling\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Billplz driver — a one-time, hosted "bill" gateway (no native subscriptions),
 * Billplz API v3 over Laravel's HTTP client.
 *
 * Config: api_key, x_signature_key, collection_id, callback_url, sandbox (bool).
 *
 * Callbacks are verified with the X Signature: HMAC-SHA256 over every posted
 * field except x_signature, each formatted "key+value", sorted ascending and
 * joined with "|".
 */
class BillplzGateway extends Gateway
{
    public function checkout(CheckoutRequest $request): CheckoutIntent
    {
        $bill = Http::withBasicAuth((string) $this->config('api_key'), '')
            ->asForm()
            ->post($this->base().'/api/v3/bills', array_filter([
                'collection_id' => (string) $this->config('collection_id'),
                'email' => $request->customerEmail,
                'name' => $request->customerName,
                'amount' => $request->amountCents,
                'description' => $request->description,
                'callback_url' => $this->callbackUrl($request),
                'redirect_url' => $request->returnUrl,
                // Billplz echoes this back on the callback and on the bill.
                // The caller's own id is what lets an application find the
                // invoice a payment belongs to without a lookup table.
                'reference_1_label' => $request->reference === null ? null : 'Reference',
                'reference_1' => $request->reference,
            ], fn ($v): bool => $v !== null))->throw()->json();

        return new CheckoutIntent((string) $bill['url'], (string) $bill['id']);
    }

    /**
     * `GET /api/v3/bills/{id}` — Billplz's own record of the bill.
     *
     * A 404 means no such bill, which is an answer. Anything else is the API
     * failing and must not be reported as "not paid".
     */
    public function fetch(string $externalId): ?PaymentStatus
    {
        $response = Http::withBasicAuth((string) $this->config('api_key'), '')
            ->get($this->base().'/api/v3/bills/'.$externalId);

        if ($response->status() === 404) {
            return null;
        }

        $bill = $response->throw()->json();

        return new PaymentStatus(
            externalId: (string) ($bill['id'] ?? $externalId),
            paid: (bool) ($bill['paid'] ?? false),
            status: (string) ($bill['state'] ?? 'unknown'),
            amountCents: isset($bill['amount']) ? (int) $bill['amount'] : null,
            currency: 'MYR',
            raw: (array) $bill,
        );
    }

    public function cancel(Subscription $subscription): void
    {
        // One-time gateway — nothing to cancel upstream.
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $source = collect($request->except('x_signature'))
            ->map(fn ($value, $key) => $key.$value)
            ->sort()
            ->implode('|');

        $expected = $this->hmac('sha256', $source, (string) $this->config('x_signature_key'));

        if (! $this->signaturesMatch($expected, (string) $request->input('x_signature'))) {
            return null;
        }

        $id = (string) $request->input('id');
        $paid = filter_var($request->input('paid'), FILTER_VALIDATE_BOOLEAN);
        $amount = (int) ($request->input('paid_amount') ?? $request->input('amount') ?? 0);

        if (! $paid) {
            return new WebhookEvent(
                type: WebhookEventType::PaymentFailed,
                externalId: $id,
                providerEventId: 'billplz-'.$id,
                rawPayload: $request->all(),
            );
        }

        return new WebhookEvent(
            type: WebhookEventType::SubscriptionActivated,
            externalId: $id,
            amountCents: $amount,
            providerEventId: 'billplz-'.$id,
            rawPayload: $request->all(),
        );
    }

    protected function base(): string
    {
        return $this->config('sandbox')
            ? 'https://www.billplz-sandbox.com'
            : 'https://www.billplz.com';
    }
}
