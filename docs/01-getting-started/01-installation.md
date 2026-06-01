# Installation

Laravel Billing installs like any Composer package. The core engine is headless; the billing UI is
opt-in and only needs extra dependencies when you enable it.

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | `^8.4` |
| Laravel | `^11.0` \|\| `^12.0` \|\| `^13.0` |
| `barryvdh/laravel-dompdf` | `^3.1` (bundled — renders invoice/receipt PDFs) |

## Install the package

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

> **Note**: A fresh install defaults to `BILLING_GATEWAY=local`, so demo, development, and UAT work
> **before** any merchant account exists. See [Gateways](../02-architecture/03-gateways-and-webhooks.md).

## Enable the billing UI (optional)

The customer-facing pages are built with Livewire and Flux. Install them in your app only if you
plan to use the bundled UI:

```bash
composer require livewire/livewire livewire/flux
```

The package registers its Livewire components and `/billing` routes automatically when Livewire is
present and `billing.routes.enabled` is `true`. To stay fully headless, leave these uninstalled or
set `BILLING_UI_ENABLED=false`. See [Billing UI](../03-billing-ui/README.md).

## Next Steps

- [Make a model billable](02-make-billable.md)
- [Define your plans](03-plans.md)
