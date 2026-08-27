# Changelog

All notable changes to `laravel-billing` will be documented in this file.

## 2.0.0 - 2026-08-27

**The one-off charge is now the primitive**, and a plan checkout is a one-off charge described by a plan.

### Why

Every bundled driver except Stripe and PayPal already created a one-off bill — Billplz posts to `/bills`, toyyibPay to `createBill`, Bayarcash to `/payment-intents`. The `Plan` and the `PlanInterval` were only ever used to derive four values: an amount, a description, who is paying, and where to send them back.

Typing the contract on `Plan` and `Billable` therefore coupled ten drivers to this package's subscription tables for no reason, and made them unusable for the other thing a host application always eventually needs: **charging for an invoice, a top-up, or a one-time fee**.

### Added

- **`DataTransferObjects\CheckoutRequest`** — one payment to collect, described with no model from this package. Amount in **minor units**, never a float. Carries the **caller's own reference**, echoed back where the gateway allows it: an application charging invoice #1042 needs the gateway to return something it can find #1042 from. Also carries an optional per-request `callbackUrl`, which an application routing webhooks per tenant cannot express in a single config value.
- **`PaymentGateway::checkout(CheckoutRequest): CheckoutIntent`** — the primitive.
- **`PaymentGateway::fetch(string $externalId): ?PaymentStatus`** — ask the gateway about a payment instead of waiting to be told. A webhook that was never delivered is otherwise indistinguishable from a payment that never happened, which is the state a customer is in when they say "I paid" and the application says otherwise. Implemented for **Billplz**, **Stripe** and **toyyibPay**.
- **`DataTransferObjects\PaymentStatus`** — `paid` is deliberately separate from `status`, so a caller does not have to learn ten gateway vocabularies to answer one question.
- **`Exceptions\UnsupportedByGateway`** — thrown by `fetch()` on a gateway with no way to be asked. Not null: null already means "asked, and there is nothing there", and only that is an answer.
- **`Gateways\Concerns\MapsPlanToCheckout`** — one copy of the plan → charge mapping, used by the `Gateway` base class and by `LocalGateway`.
- **One-off Stripe and PayPal charges.** Stripe gains `mode: payment` with `price_data`; PayPal gains the Orders API v2 with `intent: CAPTURE` (an authorisation nobody captures expires silently, which looks exactly like a payment that worked until the money never arrives). Both keep their existing native-subscription `createCheckout()` unchanged — a subscription and an ad-hoc charge are different objects at the vendor, not two spellings of one.

### Changed

- Eight drivers moved their `createCheckout()` body into `checkout()`; the plan mapping now happens once in the base class rather than in each of them.
- Drivers no longer mint an order reference unconditionally — they use the caller's when one is given.
- Payer phone is taken from the request where a gateway asks for one, instead of being hardcoded to `''` / `0000000000`.

### BREAKING

`Contracts\PaymentGateway` gained two methods, so **any host application implementing it directly must be updated**. The upgrade is small:

1. Rename your `createCheckout(Billable, Plan, PlanInterval, string)` to `checkout(CheckoutRequest $request)` and read the four values off `$request`.
2. Either extend `Gateways\Gateway` or `use Gateways\Concerns\MapsPlanToCheckout` to get `createCheckout()` back for free.
3. Add `fetch()`. If the gateway cannot be asked, `throw UnsupportedByGateway::cannot('your-gateway', 'be asked about a payment');`.

Nothing else changed: a plan checkout sends the same payload it sent in 1.0, and a test pins that.

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
