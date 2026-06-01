# Routes and Authorization

When the UI is enabled, the package registers a set of `/billing` routes behind your chosen
middleware, scoped to the resolved billable. Registration is guarded so the package stays headless
when Livewire is absent or the UI is disabled.

## Registered routes

| Method | URI | Name | Serves |
|--------|-----|------|--------|
| GET | `/billing/plans` | `billing.plans` | `Plans` — plan cards + Subscribe |
| GET | `/billing` | `billing.portal` | `BillingPortal` — Overview + Invoices |
| GET | `/billing/success` | `billing.success` | `PaymentSuccess` — receipt card |
| GET | `/billing/invoices/{invoice:uuid}/download` | `billing.invoices.download` | Streams the invoice PDF |
| GET | `/billing/invoices/{invoice:uuid}/receipt` | `billing.invoices.receipt` | Streams the receipt PDF |

The prefix (`billing`) and middleware are configurable:

```php
// config/billing.php
'routes' => [
    'enabled'    => env('BILLING_UI_ENABLED', true),
    'prefix'     => env('BILLING_UI_PREFIX', 'billing'),
    'middleware' => ['web', 'auth'],
],
```

Routes register only when `routes.enabled` is true **and** Livewire is installed.

## Resolving the billable

Every page and download resolves the billable through `BillableResolver`, which defaults to the
authenticated user. Override it to scope billing to a `Team`/`Workspace`:

```php
// config/billing.php
'billable_resolver' => fn ($request) => $request->user()->currentTeam,
```

`null` (the default) falls back to `fn ($request) => $request->user()`.

## Authorization

The download and receipt routes verify ownership before streaming. The `InvoiceController` aborts
with `403` if the invoice does not belong to the resolved billable, and `404` for a receipt of an
unpaid invoice:

```php
protected function authorizeInvoice(Request $request, Invoice $invoice): void
{
    $billable = $this->resolver->resolve($request);

    abort_if($billable === null, 403);

    abort_unless(
        $invoice->billable_type === $billable->getMorphClass()
            && (string) $invoice->billable_id === (string) $billable->getKey(),
        403
    );
}
```

Because every component query is constrained to the resolved billable, one customer can never see
or download another's invoices.

## Next Steps

- [Components and customization](03-components.md)
- [Invoices and receipts](04-invoices-and-receipts.md)
