<?php

namespace CleaniqueCoders\LaravelBilling;

use CleaniqueCoders\LaravelBilling\Commands\LaravelBillingCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelBillingServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-billing')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_billing_table')
            ->hasCommand(LaravelBillingCommand::class);
    }
}
