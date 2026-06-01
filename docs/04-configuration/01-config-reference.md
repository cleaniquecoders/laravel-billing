# Configuration Reference

All settings live in `config/billing.php` (publish with `--tag="laravel-billing-config"`). Keys are
grouped by concern below, with the backing environment variable and default.

## Gateways

```php
'default' => env('BILLING_GATEWAY', 'local'),

'gateways' => [
    'local' => [
        'driver'  => 'local',
        'enabled' => env('BILLING_LOCAL_ENABLED', true), // never serves in production
        'auto'    => env('BILLING_LOCAL_AUTO', false),    // auto-approve (CI/tests/demo)
    ],
],
```

| Key | Env | Default | Purpose |
|-----|-----|---------|---------|
| `default` | `BILLING_GATEWAY` | `local` | Active driver name |
| `gateways.local.enabled` | `BILLING_LOCAL_ENABLED` | `true` | Register the dev checkout (never in production) |
| `gateways.local.auto` | `BILLING_LOCAL_AUTO` | `false` | Auto-approve checkout synchronously |

## Plans

| Key | Env | Default | Purpose |
|-----|-----|---------|---------|
| `store` | `BILLING_PLAN_STORE` | `database` | `config` or `database` |
| `default_plan` | `BILLING_DEFAULT_PLAN` | `free` | Fallback tier when no subscription |
| `plans` | — | `['free' => …]` | The canonical plan matrix |

See [Plans](../01-getting-started/03-plans.md).

## Invoicing

```php
'currency' => env('BILLING_CURRENCY', 'MYR'),

'invoice' => [
    'prefix'       => env('BILLING_INVOICE_PREFIX', 'INV'),
    'number_pad'   => 6,
    'disk'         => env('BILLING_INVOICE_DISK', 'local'),
    'path'         => 'billing/{billable_type}/{billable_id}/invoices/{invoice_uuid}.pdf',
    'view'         => 'billing::invoice-pdf',
    'receipt_view' => 'billing::receipt-pdf',
],
```

| Key | Env | Default | Purpose |
|-----|-----|---------|---------|
| `currency` | `BILLING_CURRENCY` | `MYR` | Invoice currency code |
| `invoice.prefix` | `BILLING_INVOICE_PREFIX` | `INV` | Number prefix |
| `invoice.number_pad` | — | `6` | Zero-pad width |
| `invoice.disk` | `BILLING_INVOICE_DISK` | `local` | Filesystem disk for stored PDFs |
| `invoice.path` | — | see above | Storage path template |
| `invoice.view` | — | `billing::invoice-pdf` | Invoice PDF Blade view |
| `invoice.receipt_view` | — | `billing::receipt-pdf` | Receipt PDF Blade view |

## Tax (SST)

```php
'tax' => [
    'enabled' => env('BILLING_TAX_ENABLED', false),
    'rate'    => (float) env('BILLING_TAX_RATE', 0), // e.g. 0.08 for 8% SST
    'label'   => env('BILLING_TAX_LABEL', 'SST'),
],
```

When enabled, `IssueInvoice` records `subtotal_cents` / `tax_cents` / `tax_rate` / `tax_label`. See
[Invoices and Receipts](../03-billing-ui/04-invoices-and-receipts.md).

## Company (seller details)

`billing.company` populates the invoice/receipt "From" block: `name`, `ssm`, `sst`, `email`,
`website`, and an `address` map (`street_1`, `street_2`, `postcode`, `city`, `state`, `country`).
Each is backed by a `BILLING_COMPANY_*` env var and defaults to `null` (neutral).

## Subscription behaviour

| Key | Env | Default | Purpose |
|-----|-----|---------|---------|
| `trial_days` | `BILLING_TRIAL_DAYS` | `0` | Trial length |

When `cancel_at_period_end` is set on a subscription, access continues until `current_period_end`
(grace period).

## Customer-facing UI

```php
'routes' => [
    'enabled'    => env('BILLING_UI_ENABLED', true),
    'prefix'     => env('BILLING_UI_PREFIX', 'billing'),
    'middleware' => ['web', 'auth'],
],

'layout'            => env('BILLING_UI_LAYOUT', 'billing::layouts.app'),
'billable_resolver' => null,
```

| Key | Env | Default | Purpose |
|-----|-----|---------|---------|
| `routes.enabled` | `BILLING_UI_ENABLED` | `true` | Register the `/billing` routes |
| `routes.prefix` | `BILLING_UI_PREFIX` | `billing` | URL prefix |
| `routes.middleware` | — | `['web', 'auth']` | Middleware stack |
| `layout` | `BILLING_UI_LAYOUT` | `billing::layouts.app` | Full-page layout (needs `{{ $slot }}`) |
| `billable_resolver` | — | `null` (→ `request()->user()`) | Closure resolving the scoped billable |

See [Routes and Authorization](../03-billing-ui/02-routes-and-authorization.md).

## Models and audit

| Key | Env | Default | Purpose |
|-----|-----|---------|---------|
| `uuid` | `BILLING_UUID` | `true` | Generate UUIDs on models |
| `audit` | `BILLING_AUDIT` | `false` | Populate `created_by` / `updated_by` |
| `models.*` | — | package models | Swap the engine model classes |

`models` maps `subscription`, `invoice`, `plan`, `usage_counter`, and `invoice_sequence` to their
classes — override to extend any model.

## Next Steps

- [Workbench preview](../05-development/01-workbench-preview.md)
- [The full billing cycle](../06-examples/01-full-billing-cycle.md)
