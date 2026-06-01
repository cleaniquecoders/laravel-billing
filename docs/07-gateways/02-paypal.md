# PayPal

**Shape:** Hosted URL (approval link) · **Native recurring:** yes · **SDK (app-side):** none
required — the recipe uses Laravel's HTTP client against the PayPal REST API. (You may instead
use the official server SDK / Braintree.)

PayPal has native subscriptions via the **Subscriptions API** (billing plans). The subscription
id is known at checkout and is what every later webhook carries, so — unlike Stripe — no
id reconciliation is needed. Map each plan tier + interval to a PayPal **billing plan id**.

- Docs: <https://developer.paypal.com/braintree/docs/guides/paypal/server-side/php/>
- Orders SDK (one-off, alternative): <https://github.com/paypal/Checkout-PHP-SDK>

## Driver

Paste into `app/Billing/PayPalGateway.php`:

```php
<?php

namespace App\Billing;

use CleaniqueCoders\LaravelBilling\Contracts\Billable;
use CleaniqueCoders\LaravelBilling\Contracts\PaymentGateway;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutIntent;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\WebhookEvent;
use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
use CleaniqueCoders\LaravelBilling\Models\Plan;
use CleaniqueCoders\LaravelBilling\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PayPalGateway implements PaymentGateway
{
    /** @param array{client_id:string,client_secret:string,webhook_id:string,mode:string,plans:array<string,array<string,string>>} $config */
    public function __construct(private array $config) {}

    public function createCheckout(Billable $billable, Plan $plan, PlanInterval $interval, string $returnUrl): CheckoutIntent
    {
        $planId = $this->config['plans'][$plan->tier][$interval->value] ?? null;
        abort_if($planId === null, 500, "No PayPal plan configured for {$plan->tier}/{$interval->value}.");

        $res = Http::withToken($this->token())
            ->post($this->base().'/v1/billing/subscriptions', [
                'plan_id' => $planId,
                'custom_id' => $billable->getMorphClass().':'.$billable->getKey(),
                'subscriber' => ['email_address' => $billable->billingEmail()],
                'application_context' => ['return_url' => $returnUrl, 'cancel_url' => $returnUrl],
            ])->throw()->json();

        $approve = collect($res['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? $returnUrl;

        return new CheckoutIntent($approve, (string) $res['id']);   // id = the subscription id
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
        $verified = Http::withToken($this->token())
            ->post($this->base().'/v1/notifications/verify-webhook-signature', [
                'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
                'cert_url' => $request->header('PAYPAL-CERT-URL'),
                'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
                'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
                'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                'webhook_id' => $this->config['webhook_id'],
                'webhook_event' => $request->json()->all(),
            ])->json('verification_status');

        if ($verified !== 'SUCCESS') {
            return null;
        }

        $payload = $request->json()->all();
        $resource = $payload['resource'] ?? [];

        return match ($payload['event_type'] ?? null) {
            'BILLING.SUBSCRIPTION.ACTIVATED' => new WebhookEvent(
                type: WebhookEventType::SubscriptionActivated,
                externalId: (string) ($resource['id'] ?? ''),
                providerEventId: $payload['id'] ?? null,
                rawPayload: $payload,
            ),
            'PAYMENT.SALE.COMPLETED' => new WebhookEvent(
                type: WebhookEventType::SubscriptionRenewed,
                externalId: (string) ($resource['billing_agreement_id'] ?? ''),   // = subscription id
                amountCents: isset($resource['amount']['total'])
                    ? (int) round(((float) $resource['amount']['total']) * 100) : null,
                providerEventId: $payload['id'] ?? null,
            ),
            'BILLING.SUBSCRIPTION.PAYMENT.FAILED' => new WebhookEvent(
                type: WebhookEventType::PaymentFailed,
                externalId: (string) ($resource['id'] ?? ''),
                providerEventId: $payload['id'] ?? null,
            ),
            'BILLING.SUBSCRIPTION.CANCELLED', 'BILLING.SUBSCRIPTION.EXPIRED' => new WebhookEvent(
                type: WebhookEventType::SubscriptionCanceled,
                externalId: (string) ($resource['id'] ?? ''),
                providerEventId: $payload['id'] ?? null,
            ),
            default => null,
        };
    }

    private function base(): string
    {
        return ($this->config['mode'] ?? 'sandbox') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function token(): string
    {
        return (string) Http::asForm()
            ->withBasicAuth($this->config['client_id'], $this->config['client_secret'])
            ->post($this->base().'/v1/oauth2/token', ['grant_type' => 'client_credentials'])
            ->throw()->json('access_token');
    }
}
```

## Config

```php
// config/billing.php
'gateways' => [
    'paypal' => [
        'driver' => App\Billing\PayPalGateway::class,
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'mode' => env('PAYPAL_MODE', 'sandbox'), // 'sandbox' | 'live'
        'plans' => [
            'pro' => [
                'monthly' => env('PAYPAL_PLAN_PRO_MONTHLY'),
                'annual' => env('PAYPAL_PLAN_PRO_ANNUAL'),
            ],
        ],
    ],
],
```

```dotenv
PAYPAL_CLIENT_ID=...
PAYPAL_CLIENT_SECRET=...
PAYPAL_WEBHOOK_ID=...
PAYPAL_MODE=sandbox
PAYPAL_PLAN_PRO_MONTHLY=P-XXXX
PAYPAL_PLAN_PRO_ANNUAL=P-XXXX
```

If you prefer constructor injection of the config block, register it explicitly:

```php
Billing::extend('paypal', fn () => new App\Billing\PayPalGateway(config('billing.gateways.paypal')));
```

## Webhook route

```php
Route::post('/webhooks/paypal', function (Illuminate\Http\Request $request) {
    $event = CleaniqueCoders\LaravelBilling\Facades\Billing::gateway('paypal')->parseWebhook($request);
    abort_if($event === null, 401);
    CleaniqueCoders\LaravelBilling\Facades\Billing::handle($event);
    return response()->noContent();
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
```

## Sandbox

1. Create a **sandbox app** in the PayPal Developer dashboard → client id + secret.
2. Create product + billing **plans** (sandbox) and put their `P-…` ids in config.
3. Create a **webhook** (sandbox) pointing at `/webhooks/paypal`, subscribe to the four
   `BILLING.SUBSCRIPTION.*` + `PAYMENT.SALE.COMPLETED` events, copy its id to `PAYPAL_WEBHOOK_ID`.
4. Subscribe with a sandbox personal account; confirm `BILLING.SUBSCRIPTION.ACTIVATED` activates
   and issues the first invoice via `WebhookProcessor`.

## Renewals

**Automatic.** PayPal bills each cycle and fires `PAYMENT.SALE.COMPLETED` (carrying
`billing_agreement_id` = the subscription id) → mapped to `SubscriptionRenewed`.
