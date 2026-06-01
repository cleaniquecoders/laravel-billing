# Payment gateway recipes

`laravel-billing` is **gateway-agnostic** — the package never references a real gateway by
name. A real gateway is one small class in **your app** implementing
[`Contracts\PaymentGateway`](../../src/Contracts/PaymentGateway.php) (3 methods:
`createCheckout`, `cancel`, `parseWebhook`), registered in `config/billing.php`.

Each gateway below ships as a **real driver class** in the package
(`CleaniqueCoders\LaravelBilling\Gateways\*`), built on
[`SignsPayloads`](../../src/Gateways/Concerns/SignsPayloads.php) and the
[`RedirectForm`](../../src/Gateways/Support/RedirectForm.php) bridge. To use one you don't
write driver code — set its config block (the manager injects it), point a webhook route at
it, done. These pages document the config, webhook mapping, and sandbox per gateway. The
drivers use Laravel's HTTP client (no gateway SDK is required); official SDKs are listed only
as optional alternatives.

> You can still write your own driver instead — see
> [Write your own gateway](../06-examples/02-custom-gateway.md). The contract is identical.

## The two shapes

- **Hosted-URL** — the gateway's API returns a payment-page URL; `createCheckout()` returns
  it directly. (Stripe, PayPal, Billplz, ToyyibPay, SecurePay, BayarCash)
- **Form-POST** — you POST signed fields to an entry URL; `createCheckout()` returns
  `route('billing.gateway.redirect', ['token' => RedirectForm::sign($entryUrl, $fields)])`,
  which renders an auto-submitting form. (iPay88, eGHL, senangPay)

## Recipes

| Gateway | Shape | Native recurring | Success signal | Renewal |
|---|---|---|---|---|
| [Stripe](01-stripe.md) | Hosted URL | **Yes** | `checkout.session.completed` | Automatic (`invoice.paid`) |
| [PayPal](02-paypal.md) | Hosted URL | **Yes** | `BILLING.SUBSCRIPTION.ACTIVATED` | Automatic (`PAYMENT.SALE.COMPLETED`) |
| [iPay88](03-ipay88.md) | Form-POST | No | `Status == 1` | Re-checkout |
| [Billplz](04-billplz.md) | Hosted URL | No | `paid == true` | Re-checkout |
| [senangPay](05-senangpay.md) | Form-POST | Recurring API | `status_id == 1` | Recurring API |
| [eGHL](06-eghl.md) | Form-POST | Tokenized | `TxnStatus == 0` | Re-checkout / token |
| [ToyyibPay](07-toyyibpay.md) | Hosted URL | No | `status_id == 1` | Re-checkout |
| [SecurePay](08-securepay.md) | Hosted URL | Add-on | paid status | Re-checkout |
| [BayarCash](09-bayarcash.md) | Hosted URL | Tokenized (FPX DD) | `status == 3` | DD mandate charge |

## Webhook event mapping

Every `parseWebhook()` returns a normalised [`WebhookEvent`](../../src/DataTransferObjects/WebhookEvent.php)
whose [`WebhookEventType`](../../src/Enums/WebhookEventType.php) drives
[`WebhookProcessor`](../../src/Services/WebhookProcessor.php):

| What happened upstream | `WebhookEventType` |
|---|---|
| First successful payment / activation | `SubscriptionActivated` |
| Recurring renewal payment | `SubscriptionRenewed` |
| One-off payment cleared | `PaymentSucceeded` |
| Payment declined | `PaymentFailed` |
| Cancellation | `SubscriptionCanceled` |

## The webhook route (your app owns it)

```php
use CleaniqueCoders\LaravelBilling\Facades\Billing;
use Illuminate\Http\Request;

Route::post('/webhooks/{gateway}', function (Request $request, string $gateway) {
    $event = Billing::gateway($gateway)->parseWebhook($request);
    abort_if($event === null, 401);            // signature failed / irrelevant event

    Billing::handle($event);                    // dedups, transitions, issues invoices, fires events
    return response()->noContent();
})->name('billing.webhook');
```

## Renewals

The package is **reactive** — it never initiates a charge. So:

- **Stripe / PayPal** renew automatically; just map the recurring webhook to `SubscriptionRenewed`.
- **BayarCash / senangPay** are tokenized — charge the saved mandate/token from a scheduled
  job in your app, then feed the result back as a `SubscriptionRenewed` webhook.
- **iPay88 / Billplz / ToyyibPay / eGHL / SecurePay** are one-time — send the customer back
  through `Billing::checkout()` near `current_period_end` to renew.

> See also: [Write your own gateway](../06-examples/02-custom-gateway.md) ·
> [Gateways & webhooks](../02-architecture/03-gateways-and-webhooks.md)
