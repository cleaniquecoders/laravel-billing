<?php

namespace CleaniqueCoders\LaravelBilling\Gateways;

use CleaniqueCoders\LaravelBilling\BillingManager;
use CleaniqueCoders\LaravelBilling\Contracts\Billable;
use CleaniqueCoders\LaravelBilling\Contracts\PaymentGateway;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutIntent;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\WebhookEvent;
use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
use CleaniqueCoders\LaravelBilling\Models\Plan;
use CleaniqueCoders\LaravelBilling\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Bundled default driver. Exercises the full billing flow with no real money:
 * checkout redirects to an Approve/Decline page (or auto-approves in CI), and
 * approvals flow through the same WebhookEvent path a real gateway would use.
 * The local token is HMAC-signed with the app key so signature verification is
 * exercised too.
 */
class LocalGateway implements PaymentGateway
{
    public function createCheckout(
        Billable $billable,
        Plan $plan,
        PlanInterval $interval,
        string $returnUrl,
    ): CheckoutIntent {
        $externalId = (string) Str::orderedUuid();
        $amountCents = $plan->priceCents($interval);

        // In auto mode the manager activates immediately; the redirect is unused.
        if ($this->autoApproves()) {
            return new CheckoutIntent($returnUrl, $externalId);
        }

        $token = static::sign([
            'external_id' => $externalId,
            'amount_cents' => $amountCents,
            'return_url' => $returnUrl,
        ]);

        $redirect = URL::route('billing.local.checkout', ['token' => $token]);

        return new CheckoutIntent($redirect, $externalId);
    }

    public function cancel(Subscription $subscription): void
    {
        if ($subscription->gateway_subscription_id === null) {
            return;
        }

        app(BillingManager::class)->handle(new WebhookEvent(
            type: WebhookEventType::SubscriptionCanceled,
            externalId: $subscription->gateway_subscription_id,
            providerEventId: 'local-cancel-'.$subscription->gateway_subscription_id,
        ));
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $payload = static::verify((string) $request->input('token'));

        if ($payload === null) {
            return null;
        }

        if ($request->input('decision') === 'decline') {
            return null;
        }

        return new WebhookEvent(
            type: WebhookEventType::SubscriptionActivated,
            externalId: $payload['external_id'],
            amountCents: $payload['amount_cents'] ?? null,
            providerEventId: 'local-'.$payload['external_id'],
            rawPayload: $payload,
        );
    }

    protected function autoApproves(): bool
    {
        return (bool) config('billing.gateways.local.auto', false);
    }

    /**
     * Sign a payload into an opaque, tamper-evident local token.
     *
     * @param  array<string,mixed>  $payload
     */
    public static function sign(array $payload): string
    {
        $data = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', $data, static::key());

        return $data.'.'.$signature;
    }

    /**
     * Verify and decode a local token. Returns null if tampered/invalid.
     *
     * @return array<string,mixed>|null
     */
    public static function verify(string $token): ?array
    {
        if (! str_contains($token, '.')) {
            return null;
        }

        [$data, $signature] = explode('.', $token, 2);

        $expected = hash_hmac('sha256', $data, static::key());

        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $decoded = json_decode(base64_decode($data), true);

        return is_array($decoded) ? $decoded : null;
    }

    protected static function key(): string
    {
        return (string) config('app.key');
    }
}
