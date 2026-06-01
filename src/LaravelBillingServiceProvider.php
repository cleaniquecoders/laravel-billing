<?php

namespace CleaniqueCoders\LaravelBilling;

use CleaniqueCoders\LaravelBilling\Livewire\BillingPortal;
use CleaniqueCoders\LaravelBilling\Livewire\PaymentSuccess;
use CleaniqueCoders\LaravelBilling\Livewire\Plans;
use CleaniqueCoders\LaravelBilling\Services\IssueInvoice;
use CleaniqueCoders\LaravelBilling\Services\PlanRepository;
use CleaniqueCoders\LaravelBilling\Services\WebhookProcessor;
use CleaniqueCoders\LaravelBilling\Support\PlanLimits;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelBillingServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-billing')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations([
                'create_plans_table',
                'create_subscriptions_table',
                'create_invoices_table',
                'create_usage_counters_table',
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(PlanRepository::class);
        $this->app->singleton(PlanLimits::class);
        $this->app->singleton(WebhookProcessor::class);
        $this->app->bind(IssueInvoice::class, fn () => new IssueInvoice);

        $this->app->singleton('billing', fn ($app) => new BillingManager($app));
        $this->app->alias('billing', BillingManager::class);
    }

    public function packageBooted(): void
    {
        $this->bootGatewayRoutes();
        $this->bootLocalGatewayRoutes();
        $this->bootBillingUi();
        $this->bootPublishables();
    }

    /**
     * The gateway redirect bridge (form-POST gateways). Registered whenever
     * billing routes are enabled, independent of the Livewire UI, since
     * headless installs may still use a form-POST gateway.
     */
    protected function bootGatewayRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/gateway.php');
    }

    /**
     * Register the optional Livewire + Flux billing UI. Only wires up when
     * Livewire is installed; the routes file further guards on
     * config('billing.routes.enabled').
     */
    protected function bootBillingUi(): void
    {
        if (! class_exists(Livewire::class)) {
            return;
        }

        Livewire::component('billing.plans', Plans::class);
        Livewire::component('billing.portal', BillingPortal::class);
        Livewire::component('billing.payment-success', PaymentSuccess::class);

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }

    /**
     * The dev-checkout routes/views serve only when the local gateway is
     * enabled and the app is not in production.
     */
    protected function bootLocalGatewayRoutes(): void
    {
        if (! config('billing.gateways.local.enabled', false)) {
            return;
        }

        if ($this->app->environment('production')) {
            return;
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/local.php');
    }

    protected function bootPublishables(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../database/seeders/PlanSeeder.php.stub' => $this->seederPath('PlanSeeder.php'),
        ], 'billing-seeders');
    }

    protected function seederPath(string $file): string
    {
        return function_exists('database_path')
            ? database_path('seeders/'.$file)
            : base_path('database/seeders/'.$file);
    }
}
