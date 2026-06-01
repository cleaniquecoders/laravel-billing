# Stripe

**Driver:** `CleaniqueCoders\LaravelBilling\Gateways\StripeGateway` (ships with the package) ·
**Shape:** Hosted URL (Checkout Session) · **Native recurring:** yes · **Extra deps:** none — the
driver calls the Stripe REST API over Laravel's HTTP client.

Stripe has first-class subscriptions: a `mode=subscription` Checkout Session bills the card every
period and fires `invoice.paid` each cycle, so renewals are automatic — you only enable the driver
and point the webhook at it. Map each plan tier + interval to a Stripe **Price** id.

- Docs: <https://docs.stripe.com/get-started/development-environment?lang=php>

## Enable

```php
// config/billing.php
'default' => env('BILLING_GATEWAY', 'stripe'),

'gateways' => [
    'stripe' => [
        'driver' => CleaniqueCoders\LaravelBilling\Gateways\StripeGateway::class,
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'prices' => [
            'pro' => [
                'monthly' => env('STRIPE_PRICE_PRO_MONTHLY'),
                'annual' => env('STRIPE_PRICE_PRO_ANNUAL'),
            ],
        ],
    ],
],
```

```dotenv
BILLING_GATEWAY=stripe
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PRICE_PRO_MONTHLY=price_...
STRIPE_PRICE_PRO_ANNUAL=price_...
```

The manager injects the `stripe` config block into the driver automatically.

## Webhook route

Stripe needs the **raw** request body for signature verification, plus a one-line reconciliation so
renewals correlate: at checkout the subscription is keyed by the Checkout Session id; on activation
swap in the real Stripe subscription id that later `invoice.paid` events carry (the package exposes
it on `rawPayload`).

```php
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
use CleaniqueCoders\LaravelBilling\Facades\Billing;
use CleaniqueCoders\LaravelBilling\Models\Subscription;
use Illuminate\Http\Request;

Route::post('/webhooks/stripe', function (Request $request) {
    $event = Billing::gateway('stripe')->parseWebhook($request);
    abort_if($event === null, 401);

    Billing::handle($event);

    if ($event->type === WebhookEventType::SubscriptionActivated
        && ($subId = $event->rawPayload['data']['object']['subscription'] ?? null)) {
        Subscription::where('gateway_subscription_id', $event->externalId)
            ->update(['gateway_subscription_id' => $subId]);
    }

    return response()->noContent();
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
```

## Webhook mapping

| Stripe event | `WebhookEventType` |
|---|---|
| `checkout.session.completed` | `SubscriptionActivated` |
| `invoice.paid` | `SubscriptionRenewed` |
| `invoice.payment_failed` | `PaymentFailed` |
| `customer.subscription.deleted` | `SubscriptionCanceled` |

Signature: the driver verifies `Stripe-Signature` as HMAC-SHA256 of `{t}.{body}` against the
`webhook_secret` (Stripe's documented scheme) — no SDK needed.

## Sandbox

1. Use **test mode** keys (`sk_test_…`) and create test Prices.
2. Relay webhooks: `stripe listen --forward-to localhost:8000/webhooks/stripe` — it prints the
   `whsec_…` for `STRIPE_WEBHOOK_SECRET`.
3. Subscribe with test card `4242 4242 4242 4242`.
4. Confirm `checkout.session.completed` activates + issues an invoice; a later `invoice.paid` renews.

## Renewals

**Automatic** — Stripe bills each period and fires `invoice.paid` → `SubscriptionRenewed`.

> Tests: `tests/Feature/Gateways/StripeGatewayTest.php`.
