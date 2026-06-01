# Write Your Own Gateway

A real gateway is one class implementing `PaymentGateway`, registered in config. The package never
references a provider by name — this is the entire surface you implement.

## Implement the contract

```php
namespace App\Billing;

use CleaniqueCoders\LaravelBilling\Contracts\{Billable, PaymentGateway};
use CleaniqueCoders\LaravelBilling\DataTransferObjects\{CheckoutIntent, WebhookEvent};
use CleaniqueCoders\LaravelBilling\Enums\{PlanInterval, WebhookEventType};
use CleaniqueCoders\LaravelBilling\Models\{Plan, Subscription};
use Illuminate\Http\Request;

class BayarCashGateway implements PaymentGateway
{
    public function createCheckout(
        Billable $billable,
        Plan $plan,
        PlanInterval $interval,
        string $returnUrl,
    ): CheckoutIntent {
        // Call your SDK / HTTP API here.
        return new CheckoutIntent(redirectUrl: $url, externalId: $reference);
    }

    public function cancel(Subscription $subscription): void
    {
        // Terminate the upstream subscription / direct-debit enrolment.
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        // Verify the signature first; return null if invalid.
        return new WebhookEvent(
            type: WebhookEventType::SubscriptionActivated,
            externalId: $request->input('reference'),
            amountCents: (int) $request->input('amount') * 100,
            providerEventId: $request->input('event_id'),
            rawPayload: $request->all(),
        );
    }
}
```

## Register the driver

```php
// config/billing.php
'default'  => env('BILLING_GATEWAY', 'bayarcash'),
'gateways' => [
    'local'     => ['driver' => 'local', 'enabled' => env('BILLING_LOCAL_ENABLED', true)],
    'bayarcash' => ['driver' => App\Billing\BayarCashGateway::class],
],
```

The manager resolves the class through the container, so constructor injection works. You can also
register a driver at runtime:

```php
Billing::extend('toyyibpay', fn ($app) => new ToyyibPayGateway($app['config']['services.toyyibpay']));
```

## Wire the webhook

The package processes the normalised event; your app owns the route and any signature/tenancy
checks:

```php
Route::post('/webhooks/{gateway}', function (Request $request, string $gateway) {
    $event = Billing::gateway($gateway)->parseWebhook($request);
    abort_if($event === null, 401);

    Billing::handle($event);
    return response()->noContent();
});
```

## Map provider events

Return the `WebhookEventType` that matches what happened upstream, and `WebhookProcessor` does the
rest (status transition, invoice issuance, event dispatch):

| Provider event | `WebhookEventType` |
|----------------|--------------------|
| First successful payment / activation | `SubscriptionActivated` |
| Recurring renewal payment | `SubscriptionRenewed` |
| One-off payment cleared | `PaymentSucceeded` |
| Payment declined | `PaymentFailed` |
| Cancellation | `SubscriptionCanceled` |

## Next Steps

- [Gateways and webhooks](../02-architecture/03-gateways-and-webhooks.md)
- [The full billing cycle](01-full-billing-cycle.md)
