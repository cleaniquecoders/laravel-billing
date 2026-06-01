# Gateways and Webhooks

A gateway is the single extension point. The package never names a real provider — your app
implements one small contract and registers the driver class in config.

## The PaymentGateway contract

```php
namespace CleaniqueCoders\LaravelBilling\Contracts;

interface PaymentGateway
{
    public function createCheckout(
        Billable $billable,
        Plan $plan,
        PlanInterval $interval,
        string $returnUrl,
    ): CheckoutIntent;

    public function cancel(Subscription $subscription): void;

    public function parseWebhook(Request $request): ?WebhookEvent;
}
```

- `createCheckout()` returns a `CheckoutIntent(redirectUrl, externalId)` — where to send the
  customer, and an id echoed back by the webhook for correlation.
- `parseWebhook()` verifies the signature and normalises the payload into a `WebhookEvent`, or
  returns `null` if invalid.

See [Write your own gateway](../06-examples/02-custom-gateway.md) for a full driver.

## Registering a driver

```php
// config/billing.php
'default'  => env('BILLING_GATEWAY', 'bayarcash'),
'gateways' => [
    'local'     => ['driver' => 'local', 'enabled' => env('BILLING_LOCAL_ENABLED', true)],
    'bayarcash' => ['driver' => App\Billing\BayarCashGateway::class],
],
```

The manager resolves the class through the container (constructor injection works), and you can
also register a driver at runtime:

```php
Billing::extend('toyyibpay', fn ($app) => new ToyyibPayGateway($app['config']['services.toyyibpay']));
```

## The bundled LocalGateway

`LocalGateway` (`driver => 'local'`) is the default. It renders a dev "Approve / Decline" checkout
page (no real money) and is the bundled image you see in
[Billing UI](../03-billing-ui/01-overview.md). It never serves in production.

- **Approve** → a `SubscriptionActivated` event flows through the same path a real gateway uses →
  the subscription activates and an invoice is issued.
- **Decline** → the subscription stays `Incomplete`.
- `BILLING_LOCAL_AUTO=true` auto-approves synchronously (CI, tests, demos).

## The webhook flow

Your app owns the route; the package does the work:

```php
use CleaniqueCoders\LaravelBilling\Facades\Billing;

Route::post('/webhooks/{gateway}', function (Request $request, string $gateway) {
    $event = Billing::gateway($gateway)->parseWebhook($request);
    abort_if($event === null, 401);

    Billing::handle($event); // dedups, transitions state, issues invoices, fires events
    return response()->noContent();
});
```

`Billing::handle()` delegates to `WebhookProcessor`, which:

1. **Replay-guards** on `providerEventId` (via the cache).
2. **Locates** the subscription by `gateway_subscription_id` / `externalId`.
3. **Transitions** status and, on activate/renew, calls `IssueInvoice`.
4. **Fires** the matching event.

## Subscription lifecycle methods

Beyond checkout, the facade drives the rest of the lifecycle:

```php
Billing::cancel($subscription);                 // at period end (grace) — default
Billing::cancel($subscription, atPeriodEnd: false); // immediate + tells the gateway
Billing::resume($subscription);                 // undo a scheduled cancellation
Billing::swap($subscription, $plan, $interval); // change plan/interval (no proration in v1)
```

## Events

Listen to drive your own side effects (provision access, dunning, notifications). The package only
updates state and issues invoices.

| Event | Fired when |
|-------|-----------|
| `SubscriptionActivated` | A pending subscription becomes active |
| `SubscriptionRenewed` | A new period is billed |
| `SubscriptionCanceled` | A subscription is canceled (immediately) |
| `PaymentSucceeded` | A payment clears |
| `PaymentFailed` | A payment fails (subscription set `past_due`) |
| `InvoiceIssued` | An invoice is created and its PDF stored |

## Next Steps

- [Write your own gateway](../06-examples/02-custom-gateway.md)
- [The full billing cycle](../06-examples/01-full-billing-cycle.md)
