# PayPal

**Driver:** `CleaniqueCoders\LaravelBilling\Gateways\PayPalGateway` (ships with the package) ·
**Shape:** Hosted URL (approval link) · **Native recurring:** yes · **Extra deps:** none (REST API
over Laravel HTTP; the official server SDK / Braintree are optional alternatives).

PayPal has native subscriptions via the **Subscriptions API** (billing plans). The subscription id
is known at checkout and carried by every later webhook, so — unlike Stripe — no id reconciliation
is needed. Map each plan tier + interval to a PayPal **billing plan id**.

- Docs: <https://developer.paypal.com/braintree/docs/guides/paypal/server-side/php/>

## Enable

```php
// config/billing.php
'gateways' => [
    'paypal' => [
        'driver' => CleaniqueCoders\LaravelBilling\Gateways\PayPalGateway::class,
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

## Webhook route

Use the shared route ([index](README.md#the-webhook-route-your-app-owns-it)) with
`gateway=paypal`. The driver calls PayPal's `verify-webhook-signature` endpoint before trusting any
event, so an unverified payload returns `null` (→ 401).

## Webhook mapping

| PayPal event | `WebhookEventType` |
|---|---|
| `BILLING.SUBSCRIPTION.ACTIVATED` | `SubscriptionActivated` |
| `PAYMENT.SALE.COMPLETED` | `SubscriptionRenewed` |
| `BILLING.SUBSCRIPTION.PAYMENT.FAILED` | `PaymentFailed` |
| `BILLING.SUBSCRIPTION.CANCELLED` / `…EXPIRED` | `SubscriptionCanceled` |

## Sandbox

1. Create a **sandbox app** (client id + secret) in the PayPal Developer dashboard.
2. Create product + billing **plans** (sandbox); put their `P-…` ids in config.
3. Create a **webhook** (sandbox) → `/webhooks/paypal`, subscribe to the events above, copy its id
   to `PAYPAL_WEBHOOK_ID`.
4. Subscribe with a sandbox personal account; confirm activation issues the first invoice.

## Renewals

**Automatic** — PayPal bills each cycle and fires `PAYMENT.SALE.COMPLETED` (carrying
`billing_agreement_id` = subscription id) → `SubscriptionRenewed`.

> Tests: `tests/Feature/Gateways/PayPalGatewayTest.php`.
