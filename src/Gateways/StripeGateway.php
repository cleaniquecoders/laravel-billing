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
