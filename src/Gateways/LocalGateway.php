<?php

namespace CleaniqueCoders\LaravelBilling\Gateways;

use CleaniqueCoders\LaravelBilling\BillingManager;
use CleaniqueCoders\LaravelBilling\Contracts\PaymentGateway;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutIntent;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutRequest;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\PaymentStatus;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\WebhookEvent;
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
use CleaniqueCoders\LaravelBilling\Exceptions\UnsupportedByGateway;
use CleaniqueCoders\LaravelBilling\Gateways\Concerns\MapsPlanToCheckout;
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
    use MapsPlanToCheckout;

    public function checkout(CheckoutRequest $request): CheckoutIntent
    {
        // An ordered uuid rather than the base class's random reference: the
        // local gateway is what a fresh install and every test runs on, and a
        // sortable id makes a list of fake payments read in the order they
        // were made.
        $externalId = $request->reference ?? (string) Str::orderedUuid();

        // In auto mode the manager activates immediately; the redirect is unused.
        if ($this->autoApproves()) {
            return new CheckoutIntent($request->returnUrl, $externalId);
        }

        $token = static::sign([
            'external_id' => $externalId,
            'amount_cents' => $request->amountCents,
            'return_url' => $request->returnUrl,
        ]);

        $redirect = URL::route('billing.local.checkout', ['token' => $token]);

        return new CheckoutIntent($redirect, $externalId);
    }

    /**
     * The local gateway keeps no ledger, so it cannot answer this — and saying
     * so is better than returning a confident "not paid" about a payment that
     * a developer just approved on the dev checkout page.
     */
    public function fetch(string $externalId): ?PaymentStatus
    {
        throw UnsupportedByGateway::cannot('local', 'be asked about a payment');
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
