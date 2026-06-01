# SecurePay

**Driver:** `CleaniqueCoders\LaravelBilling\Gateways\SecurePayGateway` (ships with the package) ·
**Shape:** Form-POST (to `/api/v1/payments`) · **Native recurring:** add-on (treat base flow as
one-time) · **Extra deps:** none.

The browser POSTs signed fields to SecurePay's payment endpoint, so the driver returns the package's
[`billing.gateway.redirect`](../../routes/gateway.php) bridge URL.

- Docs: <https://docs.securepay.my/api> · <https://docs.securepay.my/api/merchant/payment>

## Enable

```php
// config/billing.php
'gateways' => [
    'securepay' => [
        'driver' => CleaniqueCoders\LaravelBilling\Gateways\SecurePayGateway::class,
        'uid' => env('SECUREPAY_UID'),
        'auth_token' => env('SECUREPAY_AUTH_TOKEN'),
        'checksum_token' => env('SECUREPAY_CHECKSUM_TOKEN'),
        'callback_url' => env('SECUREPAY_CALLBACK_URL'),     // your public webhook URL
        'sandbox' => env('SECUREPAY_SANDBOX', true),
    ],
],
```

```dotenv
SECUREPAY_UID=...
SECUREPAY_AUTH_TOKEN=...
SECUREPAY_CHECKSUM_TOKEN=...
SECUREPAY_CALLBACK_URL=https://your-app.test/webhooks/securepay
SECUREPAY_SANDBOX=true
```

`sandbox=true` targets `https://sandbox.securepay.my`.

## Checksums (verified)

HMAC-SHA256 with the **checksum token**, values joined by `|`:

- **request:** the nine fields in alphabetical key order —
  `buyer_email|buyer_name|buyer_phone|callback_url|order_number|product_description|redirect_url|transaction_amount|uid`.
- **callback:** every returned field except `checksum`, **sorted by key**, values `|`-joined.

`payment_status == "true"` → activation, else `PaymentFailed`. Use the shared
[`billing.webhook`](README.md#the-webhook-route-your-app-owns-it) route with `gateway=securepay` as
the `callback_url`.

## Sandbox

SecurePay sandbox `uid`, auth token, and checksum token from `sandbox.securepay.my`. The
`callback_url` must be publicly reachable.

## Renewals

One-time by default — **re-checkout** to renew (SecurePay's recurring is an add-on).

> Tests: `tests/Feature/Gateways/SecurePayGatewayTest.php`.
