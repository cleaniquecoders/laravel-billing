# Documentation

Laravel Billing is a gateway-agnostic subscription and invoicing engine for Laravel, with an
optional Livewire + Flux billing UI. It ships a built-in local gateway so any app has a working
**subscribe → pay → invoice → receipt** flow on day one, and treats real payment gateways as a
single contract you implement per app.

## Documentation Structure

### [01. Getting Started](01-getting-started/README.md)

Install the package, make a model billable, and define your plan matrix.

### [02. Architecture](02-architecture/README.md)

The domain model, the gateway/webhook contract, and the events the engine fires.

### [03. Billing UI](03-billing-ui/README.md)

The optional Livewire + Flux pages (plans, portal, receipt) with screenshots, the routes they
register, and how invoices and receipts are rendered and downloaded.

### [04. Configuration](04-configuration/README.md)

Every `config/billing.php` key and its environment variable.

### [05. Development](05-development/README.md)

Preview the UI locally with the Testbench workbench, and run the test suite.

### [06. Examples](06-examples/README.md)

The full billing cycle end to end, and how to write a real gateway driver.

## Quick Start

New to the package? Start with [Installation](01-getting-started/01-installation.md), then
[Make a model billable](01-getting-started/02-make-billable.md).

## Finding Information

- **Concepts and design** — see [Architecture](02-architecture/README.md).
- **Customer-facing pages** — see [Billing UI](03-billing-ui/README.md).
- **Settings and env vars** — see [Configuration](04-configuration/README.md).
- **Running and previewing locally** — see [Development](05-development/README.md).
