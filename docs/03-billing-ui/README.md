# Billing UI

The package ships an optional Livewire + Flux UI that closes the full **subscribe → pay → invoice →
receipt** loop. It is opt-in, scoped to the authenticated billable, and fully overridable.

## Table of Contents

### [1. Overview](01-overview.md)

The pages, with screenshots of each step of the flow.

### [2. Routes and Authorization](02-routes-and-authorization.md)

The routes the package registers, how they are gated, and how the billable is resolved and
protected.

### [3. Components](03-components.md)

The `Plans`, `BillingPortal`, and `PaymentSuccess` Livewire components and how to override their
views.

### [4. Invoices and Receipts](04-invoices-and-receipts.md)

How invoices and receipts are rendered, the SST tax breakdown, and download links — with sample
PDFs.

## Requirements

The UI requires `livewire/livewire` and `livewire/flux` in your app. See
[Installation](../01-getting-started/01-installation.md).

## Related Documentation

- [Development → Workbench preview](../05-development/01-workbench-preview.md) — run it locally.
- [Configuration](../04-configuration/01-config-reference.md) — the `routes`, `layout`, and
  `billable_resolver` keys.
