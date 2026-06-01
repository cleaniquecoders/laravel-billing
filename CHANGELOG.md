# Changelog

All notable changes to `laravel-billing` will be documented in this file.

## 1.1.0 - 2026-06-01

Adds **nine bundled, ready-to-use payment gateway drivers** plus the shared plumbing they build on. Fully additive — no breaking changes.

### Gateway drivers (`src/Gateways/`)

Opt-in via `config('billing.gateways.*.driver')` — none loaded unless selected. All use Laravel's HTTP client (no gateway SDK dependency) and verify provider signatures.

- **Stripe** · **PayPal** — native subscriptions (automatic renewals via webhooks)
- **iPay88** · **Billplz** · **senangPay** · **eGHL** · **ToyyibPay** · **SecurePay** · **BayarCash** — Malaysian gateways (one-time / tokenized; renewal documented per gateway)

### Shared building blocks (gateway-agnostic)

- `Gateways\Gateway` abstract base — injected config block + signing helpers
- `Gateways\Concerns\SignsPayloads` — `md5` / `sha1Base64` / `sha256` / `sha512` / `hmac` + constant-time `signaturesMatch`
- `Gateways\Support\RedirectForm` + the `billing.gateway.redirect` route — auto-submitting POST bridge for form-POST gateways
- `BillingManager` now injects each gateway's config block into class drivers (custom drivers without a `config` param are unaffected)
- `docs/07-gateways/` — per-gateway usage docs (config, webhook mapping, sandbox, renewals)

### Also

- `Billing` facade `@method` annotations for `checkout` / `cancel` / `resume` / `swap`
- CI: matrix aligned to `php: ^8.4`, dependency floors pinned (`dompdf/dompdf ^3.1`, `guzzlehttp/promises ^2.0.3`, `livewire/flux ^2.2`) so lower-bound lanes pass on PHP 8.4 / Laravel 13

**Requirements:** PHP `^8.4` · Laravel `^11 || ^12 || ^13` · UI (optional): `livewire/livewire` + `livewire/flux`

**Full changelog:** https://github.com/cleaniquecoders/laravel-billing/compare/1.0.0...1.1.0

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
