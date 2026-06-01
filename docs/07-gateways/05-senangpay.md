# senangPay

**Driver:** `CleaniqueCoders\LaravelBilling\Gateways\SenangPayGateway` (ships with the package) ·
**Shape:** Form-POST · **Native recurring:** yes (recurring API) · **Extra deps:** none.

The base flow is a signed redirect to the senangPay payment page; the driver returns the package's
[`billing.gateway.redirect`](../../routes/gateway.php) bridge URL (auto-submitting form).

- Docs: <https://guide.senangpay.com/api-guide> ·
  Reference: <https://github.com/senangpay/senangpay-payment-gateway-for-hikashop/blob/master/senangpay.php>

## Enable

```php
// config/billing.php
'gateways' => [
    'senangpay' => [
        'driver' => CleaniqueCoders\LaravelBilling\Gateways\SenangPayGateway::class,
        'merchant_id' => env('SENANGPAY_MERCHANT_ID'),
        'secret_key' => env('SENANGPAY_SECRET_KEY'),
        'sandbox' => env('SENANGPAY_SANDBOX', true),
    ],
],
```

```dotenv
SENANGPAY_MERCHANT_ID=...
SENANGPAY_SECRET_KEY=...
SENANGPAY_SANDBOX=true
```

`sandbox=true` targets `https://sandbox.senangpay.my/payment/{merchant_id}`.

## Hash (verified against the reference)

senangPay uses **MD5 with the secret key prepended**:

- request: `md5(secret . detail . amount . order_id)`
- response: `md5(secret . status_id . order_id . transaction_id . msg)`

`status_id == 1` → activation, else `PaymentFailed`. Configure your return + callback URLs in the
senangPay dashboard; use the shared [`billing.webhook`](README.md#the-webhook-route-your-app-owns-it)
route with `gateway=senangpay`.

## Sandbox

senangPay sandbox merchant id + secret key. Subscribe → auto-posted to senangPay → pay → senangPay
calls your callback with `status_id=1` → `WebhookProcessor` activates and issues the invoice.

## Renewals

senangPay supports **recurring** — set up a recurring profile and charge it via the recurring API
from a scheduled job in your app, mapping the recurring callback to `SubscriptionRenewed`. Otherwise
treat it as one-time and re-checkout.

> Tests: `tests/Feature/Gateways/SenangPayGatewayTest.php`.
