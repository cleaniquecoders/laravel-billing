<?php

namespace CleaniqueCoders\LaravelBilling;

use CleaniqueCoders\LaravelBilling\Services\IssueInvoice;
use CleaniqueCoders\LaravelBilling\Services\PlanRepository;
use CleaniqueCoders\LaravelBilling\Services\WebhookProcessor;
use CleaniqueCoders\LaravelBilling\Support\PlanLimits;
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
        $this->bootLocalGatewayRoutes();
        $this->bootPublishables();
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
