# Components

The UI is three full-page Livewire components, registered automatically when Livewire is installed.
All views are published under the `billing` namespace and can be overridden.

## Registered components

| Alias | Class | Route |
|-------|-------|-------|
| `billing.plans` | `CleaniqueCoders\LaravelBilling\Livewire\Plans` | `billing.plans` |
| `billing.portal` | `CleaniqueCoders\LaravelBilling\Livewire\BillingPortal` | `billing.portal` |
| `billing.payment-success` | `CleaniqueCoders\LaravelBilling\Livewire\PaymentSuccess` | `billing.success` |

## Plans

Lists `PlanRepository::all()` with a monthly/annual toggle. `subscribe($tier)` resolves the plan,
calls `Billing::checkout()` with `route('billing.success')` as the return URL, and redirects to the
gateway:

```php
$intent = Billing::checkout(
    $billable,
    $plan,
    PlanInterval::from($this->interval),
    route('billing.success'),
);

return redirect()->away($intent->redirectUrl);
```

## BillingPortal

Two tabs over the resolved billable's data:

- **Overview** — the current subscription, with `confirmCancel()` / `cancel()` (sets
  `cancel_at_period_end`) and `resume()`.
- **Invoices** — a table of `$billable->invoices()`; `selectInvoice($uuid)` opens a detail panel
  with the cost breakdown and a Download PDF link. The selected invoice is looked up *within* the
  billable's own invoices, so foreign UUIDs resolve to nothing.

## PaymentSuccess

The post-checkout landing page (`route('billing.success')`). It resolves the paid invoice (by
`?invoice=` UUID, else the latest paid invoice) and renders the receipt card with **Download
invoice** and **Download receipt** buttons.

## Layout

Full-page components render into the layout named by `billing.layout`
(default `billing::layouts.app`). Override it with your app's own layout — it only needs to expose a
`{{ $slot }}`:

```php
// config/billing.php
'layout' => 'components.layouts.app',
```

## Overriding views

Publish the views and edit the Blade under `resources/views/vendor/billing/`:

```bash
php artisan vendor:publish --tag="laravel-billing-views"
```

| View | File |
|------|------|
| Plans | `livewire/plans.blade.php` |
| Portal | `livewire/billing-portal.blade.php` |
| Receipt card | `livewire/payment-success.blade.php` |
| Invoice PDF | `invoice-pdf.blade.php` |
| Receipt PDF | `receipt-pdf.blade.php` |

## Next Steps

- [Invoices and receipts](04-invoices-and-receipts.md)
- [Configuration reference](../04-configuration/01-config-reference.md)
