<?php

namespace CleaniqueCoders\LaravelBilling\Gateways;

use CleaniqueCoders\LaravelBilling\Contracts\Billable;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutIntent;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutRequest;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\PaymentStatus;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\WebhookEvent;
use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
use CleaniqueCoders\LaravelBilling\Models\Plan;
use CleaniqueCoders\LaravelBilling\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Stripe driver — native subscriptions via the Stripe REST API over Laravel's
 * HTTP client (no SDK dependency). Renewals are automatic: Stripe bills each
 * period and fires invoice.paid.
 *
 * Config block: secret, webhook_secret, prices[tier][interval] => Stripe price id.
 *
 * Correlation: createCheckout stores the Checkout Session id as externalId; on
 * checkout.session.completed the app's webhook route should swap it for the real
 * subscription id (rawPayload carries data.object.subscription) so later
 * invoice.paid renewals locate the subscription. See docs/07-gateways/01-stripe.md.
 */
class StripeGateway extends Gateway
{
    protected string $base = 'https://api.stripe.com';

    public function createCheckout(Billable $billable, Plan $plan, PlanInterval $interval, string $returnUrl): CheckoutIntent
    {
        $price = $this->config("prices.{$plan->tier}.{$interval->value}");

        if (! is_string($price) || $price === '') {
            throw new RuntimeException("No Stripe price configured for {$plan->tier}/{$interval->value}.");
        }

        $session = Http::withToken((string) $this->config('secret'))
            ->asForm()
            ->post($this->base.'/v1/checkout/sessions', [
                'mode' => 'subscription',
                'line_items' => [['price' => $price, 'quantity' => 1]],
                'success_url' => $returnUrl,
                'cancel_url' => $returnUrl,
                'client_reference_id' => (string) $billable->getKey(),
                'customer_email' => $billable->billingEmail(),
                'subscription_data' => ['metadata' => [
                    'billable_type' => $billable->getMorphClass(),
                    'billable_id' => (string) $billable->getKey(),
                ]],
            ])->throw()->json();

        return new CheckoutIntent((string) $session['url'], (string) $session['id']);
    }

    /**
     * A one-off payment, not a subscription.
     *
     * Deliberately a separate code path from `createCheckout()`, which stays
     * `mode: subscription` against a **pre-configured Stripe price id**. The
     * two are genuinely different Stripe objects: a subscription renews and
     * fires `invoice.paid` forever, an ad-hoc charge with `price_data` happens
     * once. Collapsing them would mean either inventing a Stripe price for
     * every invoice, or quietly turning a one-time fee into a recurring one.
     */
    public function checkout(CheckoutRequest $request): CheckoutIntent
    {
        $session = Http::withToken((string) $this->config('secret'))
            ->asForm()
            ->post($this->base.'/v1/checkout/sessions', [
                'mode' => 'payment',
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        // Stripe wants the currency lower-cased and the amount
                        // in minor units, which is what CheckoutRequest holds.
                        'currency' => strtolower($request->currency),
                        'unit_amount' => $request->amountCents,
                        'product_data' => ['name' => $request->description],
                    ],
                ]],
                'success_url' => $request->returnUrl,
                'cancel_url' => $request->returnUrl,
                'customer_email' => $request->customerEmail,
                'client_reference_id' => $request->reference,
                'metadata' => $request->metadata,
            ])->throw()->json();

        return new CheckoutIntent((string) $session['url'], (string) $session['id']);
    }

    /**
     * `GET /v1/checkout/sessions/{id}`.
     *
     * `payment_status` is the field that answers the question — a session can
     * be `complete` with `payment_status: unpaid` when the customer chose a
     * delayed method, and reading `status` alone would report that as paid.
     */
    public function fetch(string $externalId): ?PaymentStatus
    {
        $response = Http::withToken((string) $this->config('secret'))
            ->get($this->base.'/v1/checkout/sessions/'.$externalId);

        if ($response->status() === 404) {
            return null;
        }

        $session = $response->throw()->json();
        $paymentStatus = (string) ($session['payment_status'] ?? 'unpaid');

        return new PaymentStatus(
            externalId: (string) ($session['id'] ?? $externalId),
            paid: $paymentStatus === 'paid' || $paymentStatus === 'no_payment_required',
            status: $paymentStatus,
            amountCents: isset($session['amount_total']) ? (int) $session['amount_total'] : null,
            currency: isset($session['currency']) ? strtoupper((string) $session['currency']) : null,
            raw: (array) $session,
        );
    }

    public function cancel(Subscription $subscription): void
    {
        if ($subscription->gateway_subscription_id) {
            Http::withToken((string) $this->config('secret'))
                ->delete($this->base.'/v1/subscriptions/'.$subscription->gateway_subscription_id);
        }
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        if (! $this->verifySignature($request)) {
            return null;
        }

        $payload = $request->json()->all();
        $object = $payload['data']['object'] ?? [];

        return match ($payload['type'] ?? null) {
            'checkout.session.completed' => new WebhookEvent(
                type: WebhookEventType::SubscriptionActivated,
                externalId: (string) ($object['id'] ?? ''),
                amountCents: isset($object['amount_total']) ? (int) $object['amount_total'] : null,
                providerEventId: $payload['id'] ?? null,
                rawPayload: $payload,
            ),
            'invoice.paid' => new WebhookEvent(
                type: WebhookEventType::SubscriptionRenewed,
                externalId: (string) ($object['subscription'] ?? ''),
                amountCents: isset($object['amount_paid']) ? (int) $object['amount_paid'] : null,
                providerEventId: $payload['id'] ?? null,
                rawPayload: $payload,
            ),
            'invoice.payment_failed' => new WebhookEvent(
                type: WebhookEventType::PaymentFailed,
                externalId: (string) ($object['subscription'] ?? ''),
                amountCents: isset($object['amount_due']) ? (int) $object['amount_due'] : null,
                providerEventId: $payload['id'] ?? null,
            ),
            'customer.subscription.deleted' => new WebhookEvent(
                type: WebhookEventType::SubscriptionCanceled,
                externalId: (string) ($object['id'] ?? ''),
                providerEventId: $payload['id'] ?? null,
            ),
            default => null,
        };
    }

    /**
     * Verify the Stripe-Signature header: HMAC-SHA256 of "{t}.{body}" with the
     * webhook secret must equal the v1 scheme value.
     */
    protected function verifySignature(Request $request): bool
    {
        $secret = (string) $this->config('webhook_secret');
        $header = (string) $request->header('Stripe-Signature');

        if ($secret === '' || $header === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $pair) {
            [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
            $parts[$k][] = $v;
        }

        $timestamp = $parts['t'][0] ?? null;
        $signatures = $parts['v1'] ?? [];

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        $expected = $this->hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        foreach ($signatures as $candidate) {
            if ($this->signaturesMatch($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
