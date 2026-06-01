# Make a Model Billable

Any model becomes billable by implementing the `Billable` contract and using the `HasSubscriptions`
trait. The bill target is polymorphic, so attach it to `User` (single-tenant) or
`Team`/`Workspace`/`Organization` (multi-tenant) — the package does not care.

## Implement the contract

```php
use CleaniqueCoders\LaravelBilling\Concerns\HasSubscriptions;
use CleaniqueCoders\LaravelBilling\Contracts\Billable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements Billable
{
    use HasSubscriptions;

    public function billingAddress(): array
    {
        return ['No. 1, Jalan Contoh', '50000 Kuala Lumpur', 'Malaysia'];
    }
}
```

`HasSubscriptions` satisfies the entire `Billable` contract. It provides `billingEmail()`,
`billingName()`, and `billingAddress()` defaults (override as needed), plus the subscription and
invoice accessors the engine and UI depend on.

## What the trait gives you

```php
$user->subscription();          // current access-granting Subscription, or null
$user->subscriptions();         // MorphMany of all subscriptions
$user->subscribedTo('pro');     // bool — on a specific tier
$user->onTrial();               // bool
$user->onGracePeriod();         // bool — access continues until current_period_end
$user->plan();                  // active Plan, or the configured default/free plan
$user->invoices();              // MorphMany, latest issued first
$user->canConsume('seats', 1);  // bool — gated by the active plan's limits
$user->recordUsage('seats', 1); // increment a usage meter
```

## The Billable contract

The methods the engine relies on are declared on the contract and implemented by the trait:

| Method | Returns | Purpose |
|--------|---------|---------|
| `getMorphClass()` | `string` | Polymorphic `billable_type` |
| `getKey()` | `mixed` | Polymorphic `billable_id` |
| `billingEmail()` | `string` | Where invoices are sent |
| `billingName()` | `string` | Shown on the invoice "Bill to" |
| `billingAddress()` | `array<string,string>` | Address lines on the invoice |
| `subscriptions()` | `MorphMany` | All subscriptions |
| `subscription()` | `?Subscription` | Current access-granting subscription |
| `invoices()` | `MorphMany` | Invoices, latest first |
| `plan()` | `Plan` | Active plan, or default/free |

> **Tip**: If you scope billing to a `Team` rather than the logged-in `User`, point
> `billing.billable_resolver` at it. See [Configuration](../04-configuration/01-config-reference.md).

## Next Steps

- [Define your plans](03-plans.md)
- [Subscribe flow](../06-examples/01-full-billing-cycle.md)
