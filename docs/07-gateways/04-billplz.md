# Billplz

**Driver:** `CleaniqueCoders\LaravelBilling\Gateways\BillplzGateway` (ships with the package) ·
**Shape:** Hosted URL (a "bill") · **Native recurring:** no · **Extra deps:** none (Billplz API v3
over Laravel HTTP).

- Docs: <https://www.billplz.com/api> · Reference SDK: <https://github.com/jomweb/billplz>

## Enable

```php
// config/billing.php
'gateways' => [
    'billplz' => [
        'driver' => CleaniqueCoders\LaravelBilling\Gateways\BillplzGateway::class,
        'api_key' => env('BILLPLZ_API_KEY'),
        'x_signature_key' => env('BILLPLZ_X_SIGNATURE_KEY'),
        'collection_id' => env('BILLPLZ_COLLECTION_ID'),
        'callback_url' => env('BILLPLZ_CALLBACK_URL'),       // your public webhook URL
        'sandbox' => env('BILLPLZ_SANDBOX', true),
    ],
],
```

```dotenv
BILLPLZ_API_KEY=...
BILLPLZ_X_SIGNATURE_KEY=...
BILLPLZ_COLLECTION_ID=...
BILLPLZ_CALLBACK_URL=https://your-app.test/webhooks/billplz
BILLPLZ_SANDBOX=true
```

`sandbox=true` targets `https://www.billplz-sandbox.com`. The driver creates a bill via
`POST /api/v3/bills` (Basic Auth with the API key) and returns the bill's `url` + `id`.

## X Signature (verified)

The callback is verified with **HMAC-SHA256** over every posted field except `x_signature`, each
formatted `key+value`, sorted ascending, joined with `|`, keyed by the **X Signature key**. A
payment is accepted when `paid == true`.

| Billplz callback | `WebhookEventType` |
|---|---|
| `paid == true` | `SubscriptionActivated` |
| `paid == false` | `PaymentFailed` |

Use the shared [`billing.webhook`](README.md#the-webhook-route-your-app-owns-it) route with
`gateway=billplz`, set as `callback_url`.

## Sandbox

Create a Billplz **staging** account → API key, a collection, and the X Signature key. Point
`callback_url` at your webhook and `redirect_url` (the `$returnUrl`) at the billing success page.

## Renewals

One-time only — **re-checkout** to renew (Billplz has no native recurring).

> Tests: `tests/Feature/Gateways/BillplzGatewayTest.php`.
