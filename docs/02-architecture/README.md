# Architecture

How the engine is put together: the domain model, the single gateway contract, and the events that
let your app react to billing state changes.

## Table of Contents

### [1. Overview](01-overview.md)

The design philosophy, the moving parts, and how a subscription flows from checkout to invoice.

### [2. Domain Models](02-domain-models.md)

`Plan`, `Subscription`, `Invoice`, `UsageCounter`, and `InvoiceSequence` — their fields, casts, and
relationships.

### [3. Gateways and Webhooks](03-gateways-and-webhooks.md)

The `PaymentGateway` contract, the bundled `LocalGateway`, the normalised webhook flow, and the
events the engine fires.

## Related Documentation

- [Getting Started](../01-getting-started/README.md)
- [Examples](../06-examples/README.md) — the full cycle and a custom gateway.
