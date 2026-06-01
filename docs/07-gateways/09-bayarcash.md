# BayarCash

**Driver:** `CleaniqueCoders\LaravelBilling\Gateways\BayarCashGateway` (ships with the package) ·
**Shape:** Hosted URL (FPX) · **Native recurring:** tokenized (FPX Direct Debit) · **Extra deps:**
none (Bayarcash API v3 over Laravel HTTP).

- Docs: <https://docs.bayarcash.com/355> · API: `https://api.console.bayar.cash/v3`

> ⚠️ **Confirm the callback checksum field order + `api_url` against the Bayarcash v3 docs / official
> PHP SDK.** The driver uses the field order below; adjust `$checksumKeys` if your portal differs.

## Enable

```php
// config/billing.php
'gateways' => [
    'bayarcash' => [
        'driver' => CleaniqueCoders\LaravelBilling\Gateways\BayarCashGateway::class,
        'pat' => env('BAYARCASH_PAT'),                       // Personal Access Token (bearer)
        'portal_key' => env('BAYARCASH_PORTAL_KEY'),
        'api_secret_key' => env('BAYARCASH_API_SECRET_KEY'), // checksum key
        'callback_url' => env('BAYARCASH_CALLBACK_URL'),     // your public webhook URL
        'api_url' => env('BAYARCASH_API_URL', 'https://api.console.bayar.cash/v3'),
        'payment_channel' => env('BAYARCASH_CHANNEL', 1),    // 1 = FPX
    ],
],
```

```dotenv
BAYARCASH_PAT=...
BAYARCASH_PORTAL_KEY=...
BAYARCASH_API_SECRET_KEY=...
BAYARCASH_CALLBACK_URL=https://your-app.test/webhooks/bayarcash
BAYARCASH_API_URL=https://api.console.bayar.cash/v3
```

The driver creates a payment intent (bearer PAT) and returns the hosted FPX `url`.

## Webhook (checksum + status)

Callbacks are verified with **HMAC-SHA256** over the checksum fields (values `|`-joined), keyed by
the **API Secret Key**.

| Bayarcash `status` | `WebhookEventType` |
|---|---|
| `3` (success) | `SubscriptionActivated` |
| `2` (failed) | `PaymentFailed` |
| `4` (cancelled) | `SubscriptionCanceled` |
| `0` new / `1` pending | ignored (`null`) |

Use the shared [`billing.webhook`](README.md#the-webhook-route-your-app-owns-it) route with
`gateway=bayarcash` as the `callback_url`.

## Sandbox

Use a Bayarcash sandbox PAT, portal key, and API secret key (set `api_url` to your sandbox base).
The `callback_url` must be publicly reachable; complete the FPX test-bank flow.

## Renewals

**Tokenized (FPX Direct Debit).** Enrol a mandate and charge it per period from a scheduled job in
your app, mapping the recurring callback to `SubscriptionRenewed`. Otherwise treat as one-time and
re-checkout.

> Tests: `tests/Feature/Gateways/BayarCashGatewayTest.php`.
