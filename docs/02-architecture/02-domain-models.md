# Domain Models

The engine persists five models. All table names are configurable via `billing.tables.*`, and the
model classes themselves can be swapped via `billing.models.*`.

## Plan

A plan tier with pricing, limits, and feature labels. Backed by config or the `plans` table
depending on `billing.store` (see [Plans](../01-getting-started/03-plans.md)).

| Field | Type | Notes |
|-------|------|-------|
| `tier` | `string` | Stable key (`free`, `pro`, …) |
| `name` | `string` | Display name |
| `tagline` | `?string` | Short marketing line |
| `price_cents` | `array<string,int>` | Keyed by interval (`monthly`, `annual`) |
| `limits` | `array<string,?int>` | Open meter map; `null` = unlimited |
| `features` | `array<int,string>` | Feature labels for the UI |
| `is_active` | `bool` | Hidden from listings when false |
| `sort_order` | `int` | Display order |

## Subscription

A billable's enrolment on a plan tier. The `plan_tier` is a snapshot; the live `Plan` is resolved
from the repository.

| Field | Type | Notes |
|-------|------|-------|
| `plan_tier` | `string` | Snapshot of the subscribed tier |
| `status` | `SubscriptionStatus` | `trialing` / `active` / `past_due` / `canceled` / `incomplete` |
| `interval` | `PlanInterval` | `monthly` / `annual` |
| `gateway` | `string` | Driver name that owns this subscription |
| `gateway_subscription_id` | `?string` | Upstream id, used to correlate webhooks |
| `trial_ends_at` | `?Carbon` | — |
| `current_period_start` | `Carbon` | — |
| `current_period_end` | `Carbon` | Access end during a grace period |
| `canceled_at` | `?Carbon` | — |
| `cancel_at_period_end` | `bool` | When true, access continues until `current_period_end` |

Helper methods: `grantsAccess()`, `onTrial()`, `onGracePeriod()`, `isCanceled()`, and `plan()`.

## Invoice

Issued for a subscription period. Numbers are atomic and sequential (see `InvoiceSequence`). Stores
a tax breakdown so the SST/SSM invoice template renders correctly.

| Field | Type | Notes |
|-------|------|-------|
| `number` | `string` | e.g. `INV-2026-000001` |
| `plan_tier` / `interval` | `string` / `PlanInterval` | Snapshot of what was billed |
| `period_start` / `period_end` | `Carbon` | Billing period |
| `subtotal_cents` | `int` | Pre-tax amount |
| `tax_cents` | `int` | Computed when `billing.tax.enabled` |
| `tax_rate` / `tax_label` | `?float` / `?string` | e.g. `0.08` / `SST` |
| `total_cents` | `int` | `subtotal + tax` |
| `currency` | `string` | Defaults to `billing.currency` |
| `status` | `InvoiceStatus` | `paid` / `refunded` / `void` |
| `issued_at` / `paid_at` | `Carbon` / `?Carbon` | — |
| `storage_path` | `?string` | Where the rendered PDF lives |
| `metadata` | `array` | e.g. `payment_method`, optional `line_items` |

Helper methods: `totalMajor()`, `subtotalMajor()`, `taxMajor()`, `isPaid()`, `paymentMethod()`, and
`lineItems()`. See [Invoices and Receipts](../03-billing-ui/04-invoices-and-receipts.md).

## UsageCounter

Tracks consumption per meter for a billable, gated against the active plan's `limits` by the
`PlanLimits` support class (`$user->canConsume()` / `$user->recordUsage()`).

## InvoiceSequence

A per-year row holding `next_number`. `IssueInvoice::nextNumber()` allocates numbers in a
row-locked transaction, so concurrent issuance never collides:

```php
// INV-{year}-{zero-padded sequence}
sprintf('%s-%d-%s', $prefix, $year, str_pad($number, $pad, '0', STR_PAD_LEFT));
```

## Enums

| Enum | Cases |
|------|-------|
| `PlanInterval` | `Monthly`, `Annual` (with `months()`) |
| `SubscriptionStatus` | `Trialing`, `Active`, `PastDue`, `Canceled`, `Incomplete` (with `grantsAccess()`) |
| `InvoiceStatus` | `Paid`, `Refunded`, `Void` |
| `WebhookEventType` | `SubscriptionActivated`, `SubscriptionRenewed`, `SubscriptionCanceled`, `PaymentSucceeded`, `PaymentFailed` |

## Next Steps

- [Gateways and webhooks](03-gateways-and-webhooks.md)
- [Configuration reference](../04-configuration/01-config-reference.md)
