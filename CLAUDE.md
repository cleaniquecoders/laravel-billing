# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`cleaniquecoders/laravel-billing` — a **gateway-agnostic** subscription & invoicing engine for Laravel, distributed as a single package (no per-gateway sub-packages). The headless core (models, services, contracts, events, manager, facade) is fully usable with no UI. An **optional** Livewire + Flux billing UI ships alongside and is opt-in. Requires PHP `^8.4`; supports Laravel 11/12/13.

## Commands

```bash
composer test                         # Pest suite (Livewire tested headlessly via Livewire::test)
vendor/bin/pest tests/Feature/WebhookTest.php          # run a single test file
vendor/bin/pest --filter="issues an invoice"           # run tests matching a name
composer test-coverage                # Pest with coverage
composer analyse                      # PHPStan / Larastan (level 5; src, config, database)
vendor/bin/pint                       # apply code style (Laravel preset; CI auto-commits fixes)
vendor/bin/pint --test                # check style without modifying
```

CI (GitHub Actions) runs tests on PHP 8.3/8.4/8.5 × ubuntu/windows, PHPStan on 8.5, and auto-commits Pint fixes on push. PHPStan baseline lives in `phpstan-baseline.neon`.

### Preview the UI locally (Testbench workbench)

The package ships a Testbench workbench (`workbench/`, `testbench.yaml`) so you can click through the full subscribe → pay → invoice → receipt flow with the real Flux UI:

```bash
npm install
npm run dev                                 # Vite + Tailwind (compiles Flux styles) — keep running
php vendor/bin/testbench workbench:build     # once: create sqlite db + run migrations + seed
php vendor/bin/testbench serve               # http://127.0.0.1:8000 — auto-logs in a demo billable, lands on /billing/plans
```

The workbench mirrors what a host app sets in `config/billing.php`: a demo plan matrix (`config` store), the local gateway with the dev "Approve" checkout page, and SST tax enabled.

## Architecture

The core idea: **the engine talks to gateways only through `Contracts\PaymentGateway`** (3 methods), never coupling to one. The bundled `Gateways\LocalGateway` (no real money) is the default so a fresh app runs the full flow on day one. The package also **ships nine real drivers** under `src/Gateways/` (Stripe, PayPal, iPay88, Billplz, SenangPay, eGHL, ToyyibPay, SecurePay, BayarCash) — opt-in via config, none loaded unless selected. Host apps can still register their own driver class; the contract is the same. See `docs/07-gateways/` for per-gateway config.

### The checkout → webhook → invoice flow (read these together)

This is the spine of the package and spans several files:

1. **`BillingManager::checkout()`** (`src/BillingManager.php`) — creates a pending (`Incomplete`) `Subscription`, delegates to the resolved driver's `createCheckout()`, and stores the driver's `externalId` on `gateway_subscription_id` to correlate the eventual webhook. If the gateway config has `auto=true` (CI/tests/demo), it synthesizes a `SubscriptionActivated` `WebhookEvent` immediately, routing through the *same* code path a real webhook would.
2. **The driver** returns a `CheckoutIntent` (redirect URL + external id). For real gateways the customer is redirected; the gateway later POSTs a webhook.
3. **The host app owns the webhook route.** It calls `Billing::gateway($name)->parseWebhook($request)` (driver verifies signature, normalises to a `WebhookEvent` DTO, or returns null) then `Billing::handle($event)`.
4. **`Services\WebhookProcessor::process()`** — replay-guards on `providerEventId` (via `Cache::add`), locates the subscription by `gateway_subscription_id`/`uuid`, transitions status with a `match` on `WebhookEventType`, calls `IssueInvoice` on activate/renew, and dispatches the matching domain event.
5. **`Services\IssueInvoice`** — allocates an atomic, row-locked sequential number (`INV-2026-000001` via `InvoiceSequence`), computes tax (`round(subtotal * rate)` when `billing.tax.enabled`), renders the Blade invoice to PDF (dompdf), stores it via the configured `Filesystem` disk, persists the record, and fires `InvoiceIssued`.

Both the synthetic auto-approve path and the real webhook path converge on `WebhookProcessor` — so testing with the local gateway exercises the production code path.

### Key seams

- **`BillingManager`** is a Laravel Manager: `gateway($name)` resolves & memoizes a driver from `config('billing.gateways.*.driver')` through the container. When the driver is a class, `resolve()` passes the whole gateway config block as the named `config` arg (`make($driver, ['config' => $config])`), so bundled drivers extending `Gateways\Gateway` get their settings injected; custom drivers without a `config` constructor param just ignore it. `extend($name, Closure)` registers a driver at runtime. Bound as singleton `'billing'` + aliased to the class; the `Billing` facade fronts it (`checkout`, `handle`, `cancel`, `resume`, `swap`, `gateway`, `extend`).
- **`Contracts\PaymentGateway`** is the *only* extension point — 3 methods: `createCheckout`, `cancel`, `parseWebhook`. Bundled real drivers extend the abstract `Gateways\Gateway` base (injected `$config` + the `Gateways\Concerns\SignsPayloads` hashing trait: `md5`/`sha1Base64`/`sha256`/`sha512`/`hmac` + constant-time `signaturesMatch`). They use Laravel's `Http` client (no vendor SDKs) and are tested with `Http::fake()` (`tests/Feature/Gateways/`). Two shapes: **hosted-URL** (API returns a pay URL) and **form-POST** (POST signed fields via the `Gateways\Support\RedirectForm` token → the always-on `billing.gateway.redirect` route renders an auto-submitting form). Adding a gateway = one class + one config entry.
- **`Contracts\Billable` + `Concerns\HasSubscriptions`** make any model (User, Team, Workspace…) billable. The bill target is **polymorphic** (`billable_type`/`billable_id`), so single- and multi-tenant are the same code. Gotcha: `Billable::getMorphClass()` is declared **without a return type** to stay compatible with Eloquent's `Model::getMorphClass()`.
- **Plans have two stores** (`config('billing.store')`): `config` builds read-only `Plan` instances on the fly (no `plans` table); `database` reads from the table, hydrated idempotently by the publishable `PlanSeeder`. `config('billing.plans')` is always the canonical source — go through `Services\PlanRepository` (`all()`/`find()`/`default()`), never query `Plan` directly. Limits are an open meter map; `null` = unlimited.
- **Subscription access** is decided by `SubscriptionStatus::grantsAccess()` (`Trialing`/`Active`/`PastDue` grant; `Canceled`/`Incomplete` don't) plus `onGracePeriod()` (cancel-at-period-end but period not elapsed). `HasSubscriptions::subscription()` returns the first subscription that grants access *or* is on grace period.

### Optional UI layer

Wired in `LaravelBillingServiceProvider::bootBillingUi()` **only when `class_exists(Livewire::class)`**, and `routes/web.php` further guards on `config('billing.routes.enabled')` — so headless installs never load it. Three full-page Livewire components (`Livewire\Plans`, `BillingPortal`, `PaymentSuccess`) + `Http\Controllers\InvoiceController` (`download` streams the stored invoice PDF; `receipt` streams an on-the-fly receipt PDF derived from a paid invoice via `Services\GenerateReceipt`). Every query is scoped to the billable from `config('billing.billable_resolver')` (defaults to `request()->user()`); download/receipt routes **403 on a foreign invoice, 404 on unpaid**. `routes/local.php` serves the dev "Approve/Decline" checkout page — only when `billing.gateways.local.enabled` and **not** in production.

### Conventions

- Money is integer **cents** everywhere (`price_cents`, `subtotal_cents`, `tax_cents`, `total_cents`).
- Models use `cleaniquecoders/traitify` (`InteractsWithUuid`) for UUIDs and `Concerns\InteractsWithAudit` for opt-in `created_by`/`updated_by` — prefer traitify concerns over hand-rolling.
- All models resolve their table via `config('billing.tables.*', ...)` and are overridable via `config('billing.models.*')` — reference the configured model class, not the concrete one, when locating records (see `WebhookProcessor::locate()`).
- Migrations ship as **`.stub` files** in `database/migrations/` (published into the host app). Tests load them directly via `TestCase::defineDatabaseMigrations()`; the workbench has its own `0001_..._create_billing_tables.php`.
- Arch tests (`tests/ArchTest.php`) enforce: no debug functions (`dd`/`dump`/`ray`/`var_dump`), `Contracts\*` are interfaces, `Enums\*` are enums, `Events\*` don't touch `Illuminate\Http`, `Models\*` don't use the raw `DB` facade.
- The package is unreleased (`CHANGELOG` empty) — edit migration/seeder stubs directly rather than adding new migrations.

## Domain events

Listen to these in the host app to drive side effects (provision access, dunning, notifications); the package itself only mutates subscription/invoice state and issues invoices:
`SubscriptionActivated` · `SubscriptionRenewed` · `SubscriptionCanceled` · `PaymentSucceeded` · `PaymentFailed` · `InvoiceIssued`

## Further docs

`docs/` holds the full guide (getting started, architecture, billing UI with screenshots, every config key, development, examples). `README.md` is the user-facing overview.
