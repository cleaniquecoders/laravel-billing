# Changelog

All notable changes to `laravel-billing` will be documented in this file.

## 1.0.0 - 2026-06-01

First stable release of **Laravel Billing** — a gateway-agnostic subscription & invoicing engine for Laravel, with an optional Livewire + Flux billing UI.

### Highlights

- **Full cycle**: subscribe → pay → invoice → receipt, working on a fresh install.
- **One package, one contract**: real gateways implement a single `PaymentGateway` contract — no per-gateway sub-packages.
- **Bundled local gateway**: dev checkout (no real money) for demo / UAT / CI.
- **Optional Livewire + Flux UI**: plans, billing portal (overview + invoices), and a payment-success receipt card — opt-in, scoped to the authenticated billable, fully overridable.
- **SST-aware invoicing**: atomic sequential numbering, subtotal/tax breakdown, PDF invoice + on-the-fly receipt with ownership-checked downloads.
- **Polymorphic billable**: attach to `User`, `Team`, or any model via the `Billable` contract + `HasSubscriptions` trait.
- **Testbench workbench**: preview the full UI locally (`testbench serve`).

### Requirements

- PHP `^8.4`
- Laravel `^11 || ^12 || ^13`
- UI (optional): `livewire/livewire` + `livewire/flux`

### Documentation

See [`docs/`](https://github.com/cleaniquecoders/laravel-billing/tree/main/docs) — getting started, architecture, billing UI, configuration, development, and examples.
