# Plans

`config('billing.plans')` is always the canonical source of plan definitions. The `store` setting
decides where reads come from at runtime.

## Config vs database store

| Store | Behaviour | Plans table |
|-------|-----------|-------------|
| `config` | `Plan` instances are built on the fly from the config array | Not needed |
| `database` | Plans are read from the `plans` table; the publishable `PlanSeeder` hydrates it idempotently from config (`updateOrCreate` on `tier`) | Required |

Set it with `BILLING_PLAN_STORE=config` or `database`.

## Defining plans

A plan declares a price per interval, optional limits, and feature labels:

```php
// config/billing.php
'plans' => [
    'free' => [
        'name' => 'Free',
        'tagline' => 'Get started',
        'price_cents' => ['monthly' => 0, 'annual' => 0],
        'limits' => ['seats' => 1],
        'features' => ['1 seat', 'Community support'],
        'is_active' => true,
        'sort_order' => 0,
    ],
    'pro' => [
        'name' => 'Pro',
        'tagline' => 'For growing teams',
        'price_cents' => ['monthly' => 4900, 'annual' => 49000],
        'limits' => ['seats' => 10],
        'features' => ['10 seats', 'Priority support', 'Custom invoices'],
        'is_active' => true,
        'sort_order' => 1,
    ],
],
```

Prices are stored in **cents** (`4900` = 49.00). Limits are an open map (`seats`,
`messages_per_month`, `api_calls`, …) — your app declares which meters exist. A `null` limit means
**unlimited**.

## Reading plans

Resolve plans through the `PlanRepository`, regardless of the active store:

```php
use CleaniqueCoders\LaravelBilling\Services\PlanRepository;

$plans = app(PlanRepository::class)->all();        // Collection<Plan>, sorted by sort_order
$plan  = app(PlanRepository::class)->find('pro');   // ?Plan
$free  = app(PlanRepository::class)->default();      // Plan (config('billing.default_plan'))
```

The `Plan` model exposes helpers used throughout the engine and UI:

```php
$plan->priceCents(PlanInterval::Monthly);  // int (cents) for an interval
$plan->limit('seats');                      // ?int — null means unlimited
$plan->hasFeature('SSO');                   // bool
$plan->isFree();                            // bool — zero on every interval
```

## Next Steps

- [Architecture overview](../02-architecture/01-overview.md)
- [Configuration reference](../04-configuration/01-config-reference.md)
