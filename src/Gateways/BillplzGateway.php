<?php

namespace CleaniqueCoders\LaravelBilling\Gateways;

use CleaniqueCoders\LaravelBilling\Contracts\Billable;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutIntent;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\WebhookEvent;
use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
use CleaniqueCoders\LaravelBilling\Models\Plan;
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
    public function createCheckout(Billable $billable, Plan $plan, PlanInterval $interval, string $returnUrl): CheckoutIntent
    {
        $bill = Http::withBasicAuth((string) $this->config('api_key'), '')
            ->asForm()
            ->post($this->base().'/api/v3/bills', [
                'collection_id' => (string) $this->config('collection_id'),
                'email' => $billable->billingEmail(),
                'name' => $billable->billingName(),
                'amount' => $plan->priceCents($interval),
                'description' => $plan->name.' ('.$interval->value.')',
                'callback_url' => (string) $this->config('callback_url'),
                'redirect_url' => $returnUrl,
            ])->throw()->json();

        return new CheckoutIntent((string) $bill['url'], (string) $bill['id']);
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
