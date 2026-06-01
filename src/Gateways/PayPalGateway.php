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
 * PayPal driver — native subscriptions via the PayPal REST Subscriptions API over
 * Laravel's HTTP client (no SDK). The subscription id is known at checkout and is
 * echoed by every later webhook, so no id reconciliation is needed.
 *
 * Config: client_id, client_secret, webhook_id, mode (sandbox|live),
 * plans[tier][interval] => PayPal billing plan id.
 */
class PayPalGateway extends Gateway
{
    public function createCheckout(Billable $billable, Plan $plan, PlanInterval $interval, string $returnUrl): CheckoutIntent
    {
        $planId = $this->config("plans.{$plan->tier}.{$interval->value}");

        if (! is_string($planId) || $planId === '') {
            throw new RuntimeException("No PayPal plan configured for {$plan->tier}/{$interval->value}.");
        }

        $res = Http::withToken($this->token())
            ->post($this->base().'/v1/billing/subscriptions', [
                'plan_id' => $planId,
                'custom_id' => $billable->getMorphClass().':'.$billable->getKey(),
                'subscriber' => ['email_address' => $billable->billingEmail()],
                'application_context' => ['return_url' => $returnUrl, 'cancel_url' => $returnUrl],
            ])->throw()->json();

        $approve = data_get(collect($res['links'] ?? [])->firstWhere('rel', 'approve'), 'href', $returnUrl);

        return new CheckoutIntent((string) $approve, (string) ($res['id'] ?? ''));
    }

    public function cancel(Subscription $subscription): void
    {
        if ($subscription->gateway_subscription_id) {
            Http::withToken($this->token())->post(
                $this->base()."/v1/billing/subscriptions/{$subscription->gateway_subscription_id}/cancel",
                ['reason' => 'Cancelled by customer'],
            );
        }
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $payload = $request->json()->all();

        if (! $this->verifySignature($request, $payload)) {
            return null;
        }

        $resource = $payload['resource'] ?? [];
        $eventId = $payload['id'] ?? null;

        return match ($payload['event_type'] ?? null) {
            'BILLING.SUBSCRIPTION.ACTIVATED' => new WebhookEvent(
                type: WebhookEventType::SubscriptionActivated,
                externalId: (string) ($resource['id'] ?? ''),
                providerEventId: $eventId,
                rawPayload: $payload,
            ),
            'PAYMENT.SALE.COMPLETED' => new WebhookEvent(
                type: WebhookEventType::SubscriptionRenewed,
                externalId: (string) ($resource['billing_agreement_id'] ?? ''),
                amountCents: isset($resource['amount']['total'])
                    ? (int) round(((float) $resource['amount']['total']) * 100) : null,
                providerEventId: $eventId,
                rawPayload: $payload,
            ),
            'BILLING.SUBSCRIPTION.PAYMENT.FAILED' => new WebhookEvent(
                type: WebhookEventType::PaymentFailed,
                externalId: (string) ($resource['id'] ?? ''),
                providerEventId: $eventId,
            ),
            'BILLING.SUBSCRIPTION.CANCELLED', 'BILLING.SUBSCRIPTION.EXPIRED' => new WebhookEvent(
                type: WebhookEventType::SubscriptionCanceled,
                externalId: (string) ($resource['id'] ?? ''),
                providerEventId: $eventId,
            ),
            default => null,
        };
    }

    /**
     * Verify the webhook with PayPal's verify-webhook-signature endpoint.
     *
     * @param  array<string,mixed>  $payload
     */
    protected function verifySignature(Request $request, array $payload): bool
    {
        $status = Http::withToken($this->token())
            ->post($this->base().'/v1/notifications/verify-webhook-signature', [
                'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
                'cert_url' => $request->header('PAYPAL-CERT-URL'),
                'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
                'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
                'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                'webhook_id' => $this->config('webhook_id'),
                'webhook_event' => $payload,
            ])->json('verification_status');

        return $status === 'SUCCESS';
    }

    protected function base(): string
    {
        return $this->config('mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    protected function token(): string
    {
        return (string) Http::asForm()
            ->withBasicAuth((string) $this->config('client_id'), (string) $this->config('client_secret'))
            ->post($this->base().'/v1/oauth2/token', ['grant_type' => 'client_credentials'])
            ->throw()->json('access_token');
    }
}
