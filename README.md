# Laravel Billing

A headless, gateway-agnostic subscription & invoicing engine for Laravel. **One package to maintain** — payment gateways are an extension point (a contract), not separate packages. Ships a built-in **local** gateway so any app has working billing on day one — ideal for demo, development, UAT, and testing.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/cleaniquecoders/laravel-billing.svg?style=flat-square)](https://packagist.org/packages/cleaniquecoders/laravel-billing)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/cleaniquecoders/laravel-billing/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/cleaniquecoders/laravel-billing/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/cleaniquecoders/laravel-billing.svg?style=flat-square)](https://packagist.org/packages/cleaniquecoders/laravel-billing)

## Why this design

- **One package, one repo.** No per-gateway sub-packages. BayarCash, ToyyibPay, Chip, senangPay, Stripe… each app writes a small driver class implementing the `PaymentGateway` contract. The package never references a real gateway by name.
- **Batteries included.** A first-class `LocalGateway` driver (no real money) is the default, so a fresh app can run the full subscribe → activate → invoice flow immediately.
- **Headless.** Models, services, contract, events, manager. No UI, no tenancy gating. Your app owns routes, UI, and access control.
- **Tenancy optional.** The bill target is polymorphic: attach `Billable` to `User` (single-tenant) or `Team`/`Workspace`/`Organization` (multi-tenant). The package does not care.
- **Malaysia-friendly invoicing.** MYR default, SST/SSM-aware invoice template, atomic sequential numbering — all configurable and neutral by default.

## Installation

```bash
composer require cleaniquecoders/laravel-billing
```

Publish the config and (if using the database plan store) the migrations:

```bash
php artisan vendor:publish --tag="laravel-billing-config"
php artisan vendor:publish --tag="laravel-billing-migrations"
php artisan migrate
```

Optionally publish the invoice/checkout views and the plan seeder:

```bash
php artisan vendor:publish --tag="laravel-billing-views"
php artisan vendor:publish --tag="billing-seeders"
```

> A fresh install defaults to `BILLING_GATEWAY=local`, so demo/UAT works **before** any merchant account exists.

## Make a model billable

Any model becomes billable by implementing the `Billable` contract and using the `HasSubscriptions` trait.

```php
use CleaniqueCoders\LaravelBilling\Contracts\Billable;
use CleaniqueCoders\LaravelBilling\Concerns\HasSubscriptions;

class User extends Authenticatable implements Billable
{
    use HasSubscriptions;

    public function billingAddress(): array
    {
        return ['No. 1, Jalan Contoh', '50000 Kuala Lumpur'];
    }
}
```

`HasSubscriptions` provides `billingEmail()`, `billingName()` and `billingAddress()` defaults (override as needed) plus:

```php
$user->subscription();              // current access-granting subscription, or null
$user->subscribedTo('pro');         // bool
$user->onTrial();                   // bool
$user->onGracePeriod();             // bool (access until current_period_end)
$user->plan();                      // active Plan, or the configured default/free
$user->invoices();                  // MorphMany
$user->canConsume('seats', 1);      // gated by plan limits
$user->recordUsage('seats', 1);
```

## Plans — config and/or database

`config('billing.plans')` is always the canonical source. The `store` setting decides where reads come from:

- `store = 'config'` — `Plan` instances are built on the fly from the array. **No `plans` table needed.**
- `store = 'database'` — plans are read from the `plans` table; the publishable `PlanSeeder` hydrates it idempotently from config (`updateOrCreate` on `tier`).

```php
use CleaniqueCoders\LaravelBilling\Services\PlanRepository;

$plans = app(PlanRepository::class)->all();          // Collection<Plan>
$plan  = app(PlanRepository::class)->find('pro');     // ?Plan
$free  = app(PlanRepository::class)->default();        // Plan
```

Limits are an open map (`seats`, `messages_per_month`, `api_calls`, …) — your app declares which meters exist. A `null` limit means **unlimited**.

## Subscribe flow

```php
use CleaniqueCoders\LaravelBilling\Facades\Billing;
use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;

$plan = app(PlanRepository::class)->find('pro');

$intent = Billing::checkout($user, $plan, PlanInterval::Monthly, route('billing.done'));

return redirect($intent->redirectUrl);
```

`checkout()` creates a pending (`Incomplete`) subscription, delegates to the active driver, and correlates the eventual webhook by `externalId`. With the local gateway:

- **Approve** on the dev-checkout page → a `SubscriptionActivated` event flows through the same code path a real gateway uses → subscription activates and an invoice is issued.
- **Decline** → the subscription stays `Incomplete`.
- Set `BILLING_LOCAL_AUTO=true` to auto-approve (great for CI/tests).

## Webhooks (your app wires the route, the package does the work)

```php
use CleaniqueCoders\LaravelBilling\Facades\Billing;

Route::post('/webhooks/{gateway}', function (Request $request, string $gateway) {
    $event = Billing::gateway($gateway)->parseWebhook($request);
    abort_if($event === null, 401);

    Billing::handle($event);   // dedups on providerEventId, transitions state, issues invoices, fires events
    return response()->noContent();
});
```

`Billing::handle()` replay-guards on `providerEventId`, locates the subscription by `gateway_subscription_id`/`externalId`, transitions status, calls `IssueInvoice` on activate/renew, and fires the matching event.

## Write your own gateway

Implement the single extension point and register the driver class in config. That's the entire surface.

```php
namespace App\Billing;

use CleaniqueCoders\LaravelBilling\Contracts\{Billable, PaymentGateway};
use CleaniqueCoders\LaravelBilling\DataTransferObjects\{CheckoutIntent, WebhookEvent};
use CleaniqueCoders\LaravelBilling\Enums\{PlanInterval, WebhookEventType};
use CleaniqueCoders\LaravelBilling\Models\{Plan, Subscription};
use Illuminate\Http\Request;

class BayarCashGateway implements PaymentGateway
{
    public function createCheckout(Billable $billable, Plan $plan, PlanInterval $interval, string $returnUrl): CheckoutIntent
    {
        // call your SDK / HTTP here
        return new CheckoutIntent(redirectUrl: $url, externalId: $reference);
    }

    public function cancel(Subscription $subscription): void { /* … */ }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        // verify signature; return null if invalid
        return new WebhookEvent(
            type: WebhookEventType::SubscriptionActivated,
            externalId: $request->input('reference'),
            amountCents: (int) $request->input('amount') * 100,
            providerEventId: $request->input('event_id'),
            rawPayload: $request->all(),
        );
    }
}
```

```php
// config/billing.php
'default'  => env('BILLING_GATEWAY', 'bayarcash'),
'gateways' => [
    'local'     => ['driver' => 'local', 'enabled' => env('BILLING_LOCAL_ENABLED', true)],
    'bayarcash' => ['driver' => App\Billing\BayarCashGateway::class],
],
```

The manager resolves the class through the container, so constructor injection works. You can also register a driver at runtime:

```php
Billing::extend('toyyibpay', fn ($app) => new ToyyibPayGateway($app['config']['services.toyyibpay']));
```

## Events

Listen to drive your own side effects (provision access, dunning, Slack notifications). The package itself only updates subscription/invoice state and issues invoices.

`SubscriptionActivated` · `SubscriptionRenewed` · `SubscriptionCanceled` · `PaymentSucceeded` · `PaymentFailed` · `InvoiceIssued`

## Invoicing

Invoices use atomic, row-locked sequential numbering (`INV-2026-000001`) and are rendered to PDF from a brandless, SST/SSM-aware Blade template, stored via Laravel's `Filesystem` (disk configurable). Set seller details under `config('billing.company')`. Publish and override `resources/views/vendor/billing/invoice-pdf.blade.php` to brand it.

## Component ownership — package vs your app

| Lives in the package | Lives in your app |
|---|---|
| `Contracts\PaymentGateway`, `Contracts\Billable` | Concrete gateway drivers (`BayarCashGateway`, …) |
| `Gateways\LocalGateway` (bundled default) | The real gateway SDK dependency |
| Models, migrations, enums, DTOs, events | The `Billable` model + `use HasSubscriptions` |
| `IssueInvoice`, `PlanRepository`, `BillingManager`, facade | Routes, UI (Livewire/Blade), access-control middleware |
| Webhook dispatcher `Billing::handle()` | The webhook route + any environment/tenancy gating |
| Brandless invoice PDF template + `InvoiceIssuedMail` | Company/tax config values, branded template override |

> **UI:** the package ships no Livewire/Blade components beyond the dev-only local checkout page. Build your billing pages in your app.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [Nasrul Hazim Bin Mohamad](https://github.com/nasrulhazim)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
