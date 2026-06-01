<?php

namespace CleaniqueCoders\LaravelBilling;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use CleaniqueCoders\LaravelBilling\Commands\LaravelBillingCommand;

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
