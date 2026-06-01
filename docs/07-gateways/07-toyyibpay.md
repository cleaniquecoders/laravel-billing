# toyyibPay

**Driver:** `CleaniqueCoders\LaravelBilling\Gateways\ToyyibPayGateway` (ships with the package) ·
**Shape:** Hosted URL (a "bill") · **Native recurring:** no · **Extra deps:** none.

- Docs: <https://toyyibpay.com/apireference/> · Reference: <https://github.com/xputerax/toyyibpay>

## Enable

```php
// config/billing.php
'gateways' => [
    'toyyibpay' => [
        'driver' => CleaniqueCoders\LaravelBilling\Gateways\ToyyibPayGateway::class,
        'secret_key' => env('TOYYIBPAY_SECRET_KEY'),
        'category_code' => env('TOYYIBPAY_CATEGORY_CODE'),
        'callback_url' => env('TOYYIBPAY_CALLBACK_URL'),      // your public webhook URL
        'sandbox' => env('TOYYIBPAY_SANDBOX', true),
    ],
],
```

```dotenv
TOYYIBPAY_SECRET_KEY=...
TOYYIBPAY_CATEGORY_CODE=...
TOYYIBPAY_CALLBACK_URL=https://your-app.test/webhooks/toyyibpay
TOYYIBPAY_SANDBOX=true
```

`sandbox=true` targets `https://dev.toyyibpay.com`. The driver calls `createBill` and returns the
hosted payment URL `{base}/{billCode}` (the `billCode` is the `externalId`).

## Webhook (confirmed by re-query)

toyyibPay callbacks are **not signed**, so the driver re-queries `getBillTransactions(billCode)` and
only activates when `billpaymentStatus == 1` — a spoofed callback alone is never trusted.

| Confirmed state | `WebhookEventType` |
|---|---|
| `billpaymentStatus == 1` | `SubscriptionActivated` |
| callback `status_id == 3`, unpaid | `PaymentFailed` |
| pending / unconfirmed | ignored (`null`) |

Use the shared [`billing.webhook`](README.md#the-webhook-route-your-app-owns-it) route with
`gateway=toyyibpay` as the `callback_url`.

## Sandbox

Create a `dev.toyyibpay.com` account → user secret key + a category code. The `callback_url` must be
publicly reachable.

## Renewals

One-time only — **re-checkout** to renew.

> Tests: `tests/Feature/Gateways/ToyyibPayGatewayTest.php`.
