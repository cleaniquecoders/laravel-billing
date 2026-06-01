# eGHL

**Driver:** `CleaniqueCoders\LaravelBilling\Gateways\EghlGateway` (ships with the package) ·
**Shape:** Form-POST · **Native recurring:** tokenized option (treat base flow as one-time) ·
**Extra deps:** none.

The browser POSTs hashed fields to the eGHL payment page; the driver returns the package's
[`billing.gateway.redirect`](../../routes/gateway.php) bridge URL (auto-submitting form).

- Spec: eGHL Bank-Direct API (e.g. v2.8 integration guide from your eGHL onboarding).

> ⚠️ **Confirm the HashValue field order + `payment_url` against your eGHL integration guide.**
> eGHL varies the concatenation across products/versions. The driver uses the common scheme below;
> adjust `requestHash()` / `responseHash()` if your merchant profile differs.

## Enable

```php
// config/billing.php
'gateways' => [
    'eghl' => [
        'driver' => CleaniqueCoders\LaravelBilling\Gateways\EghlGateway::class,
        'service_id' => env('EGHL_SERVICE_ID'),
        'password' => env('EGHL_PASSWORD'),
        'payment_url' => env('EGHL_PAYMENT_URL'),            // from eGHL onboarding (sandbox vs prod)
        'callback_url' => env('EGHL_CALLBACK_URL'),          // your public webhook URL
        'hash_algo' => env('EGHL_HASH', 'sha256'),           // 'sha256' | 'sha512'
    ],
],
```

```dotenv
EGHL_SERVICE_ID=...
EGHL_PASSWORD=...
EGHL_PAYMENT_URL=https://test2pay.ghl.com/IPGSG/Payment.aspx
EGHL_CALLBACK_URL=https://your-app.test/webhooks/eghl
EGHL_HASH=sha256
```

## Hash (default scheme)

- request: `hash(password . service_id . payment_id . return_url . amount . currency)`
- response: `hash(password . txn_id . service_id . payment_id . txn_status . amount . currency . auth_code)`

`TxnStatus == 0` → activation, else `PaymentFailed`. Use the shared
[`billing.webhook`](README.md#the-webhook-route-your-app-owns-it) route with `gateway=eghl` as the
`callback_url`.

## Sandbox

Use your eGHL **test** ServiceID + password + test payment URL and the test card set from the
integration pack. The `callback_url` must be publicly reachable.

## Renewals

One-time by default — **re-checkout** to renew. If your merchant profile has card tokenization,
charge the saved token from an app job and map the result to `SubscriptionRenewed`.

> Tests: `tests/Feature/Gateways/EghlGatewayTest.php`.
