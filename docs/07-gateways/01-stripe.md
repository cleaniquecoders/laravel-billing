# Stripe

**Shape:** Hosted URL (Checkout Session) · **Native recurring:** yes (true subscriptions) ·
**SDK (app-side):** `composer require stripe/stripe-php`

Stripe has first-class subscriptions: a `mode=subscription` Checkout Session bills the card
every period and fires `invoice.paid` each cycle, so renewals are automatic — you only map
webhooks. Map each plan tier + interval to a Stripe **Price** id.

- Docs: <https://docs.stripe.com/get-started/development-environment?lang=php>
- SDK: <https://github.com/stripe/stripe-php>

## Driver

Paste into `app/Billing/StripeGateway.php`:

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
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeGateway implements PaymentGateway
{
    /** @param array{secret:string,webhook_secret:string,prices:array<string,array<string,string>>} $config */
    public function __construct(private array $config) {}

    public function createCheckout(Billable $billable, Plan $plan, PlanInterval $interval, string $returnUrl): CheckoutIntent
    {
        $price = $this->config['prices'][$plan->tier][$interval->value] ?? null;
        abort_if($price === null, 500, "No Stripe price configured for {$plan->tier}/{$interval->value}.");

        $session = $this->client()->checkout->sessions->create([
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
        ]);

        // externalId = the Checkout Session id; reconciled to the real subscription
        // id on checkout.session.completed (see the webhook route below).
        return new CheckoutIntent($session->url, $session->id);
    }

    public function cancel(Subscription $subscription): void
    {
        if ($subscription->gateway_subscription_id) {
            $this->client()->subscriptions->cancel($subscription->gateway_subscription_id);
        }
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $this->config['webhook_secret'],
            );
        } catch (SignatureVerificationException) {
            return null;
        }

        $object = $event->data->object;

        return match ($event->type) {
            'checkout.session.completed' => new WebhookEvent(
                type: WebhookEventType::SubscriptionActivated,
                externalId: $object->id,                 // the session id (matches createCheckout)
                amountCents: $object->amount_total,
                providerEventId: $event->id,
                rawPayload: $event->toArray(),           // carries ->subscription for reconciliation
            ),
            'invoice.paid' => new WebhookEvent(
                type: WebhookEventType::SubscriptionRenewed,
                externalId: (string) $object->subscription,
                amountCents: $object->amount_paid,
                providerEventId: $event->id,
            ),
            'invoice.payment_failed' => new WebhookEvent(
                type: WebhookEventType::PaymentFailed,
                externalId: (string) $object->subscription,
                amountCents: $object->amount_due,
                providerEventId: $event->id,
            ),
            'customer.subscription.deleted' => new WebhookEvent(
                type: WebhookEventType::SubscriptionCanceled,
                externalId: (string) $object->id,
                providerEventId: $event->id,
            ),
            default => null,
        };
    }

    private function client(): StripeClient
    {
        return new StripeClient($this->config['secret']);
    }
}
```

## Config

```php
// config/billing.php
'default' => env('BILLING_GATEWAY', 'stripe'),

'gateways' => [
    'stripe' => [
        'driver' => App\Billing\StripeGateway::class,
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

The manager resolves the driver through the container — inject the config block:

```php
// A service provider, if you prefer constructor config over resolving inside the driver:
Billing::extend('stripe', fn () => new App\Billing\StripeGateway(config('billing.gateways.stripe')));
```

```dotenv
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PRICE_PRO_MONTHLY=price_...
STRIPE_PRICE_PRO_ANNUAL=price_...
```

## Webhook route

Stripe needs the **raw** request body for signature verification (don't let middleware
re-encode it), and a one-line reconciliation so renewals correlate: at checkout the
subscription is keyed by the Checkout Session id; on activation swap in the real Stripe
subscription id that later `invoice.paid` events carry.

```php
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
use CleaniqueCoders\LaravelBilling\Facades\Billing;
use CleaniqueCoders\LaravelBilling\Models\Subscription;
use Illuminate\Http\Request;

Route::post('/webhooks/stripe', function (Request $request) {
    $event = Billing::gateway('stripe')->parseWebhook($request);
    abort_if($event === null, 401);

    Billing::handle($event);

    // Reconcile session id -> real subscription id so invoice.paid renewals locate it.
    if ($event->type === WebhookEventType::SubscriptionActivated
        && ($subId = $event->rawPayload['data']['object']['subscription'] ?? null)) {
        Subscription::where('gateway_subscription_id', $event->externalId)
            ->update(['gateway_subscription_id' => $subId]);
    }

    return response()->noContent();
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
```

## Sandbox

1. Use **test mode** keys (`sk_test_…`) and create test Prices.
2. Relay webhooks locally: `stripe listen --forward-to localhost:8000/webhooks/stripe` — it
   prints the `whsec_…` signing secret for `STRIPE_WEBHOOK_SECRET`.
3. Subscribe with test card `4242 4242 4242 4242`, any future expiry/CVC.
4. Confirm: `checkout.session.completed` activates the subscription and issues an invoice via
   `WebhookProcessor`; a later `invoice.paid` renews it.

## Renewals

**Automatic.** Stripe bills each period and fires `invoice.paid` → mapped to
`SubscriptionRenewed` (the package extends the period and issues the next invoice). Nothing
app-side to schedule.
