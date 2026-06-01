<?php

namespace CleaniqueCoders\LaravelBilling\Tests;

use Barryvdh\DomPDF\ServiceProvider;
use CleaniqueCoders\LaravelBilling\LaravelBillingServiceProvider;
use CleaniqueCoders\Traitify\TraitifyServiceProvider;
use Flux\FluxServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'CleaniqueCoders\\LaravelBilling\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return array_values(array_filter([
            TraitifyServiceProvider::class,
            ServiceProvider::class,
            class_exists(LivewireServiceProvider::class) ? LivewireServiceProvider::class : null,
            class_exists(FluxServiceProvider::class) ? FluxServiceProvider::class : null,
            LaravelBillingServiceProvider::class,
        ]));
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // Keep the suite hermetic: the workbench's skeleton .env defaults cache
        // and queue to the database driver, which has no table in the in-memory
        // test DB. Pin in-memory drivers so behaviour is independent of .env.
        config()->set('cache.default', 'array');
        config()->set('session.driver', 'array');
        config()->set('queue.default', 'sync');
    }

    protected function defineDatabaseMigrations(): void
    {
        // Minimal billable owner table for the fixture User.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        // Run the package's published migration stubs.
        foreach (File::allFiles(__DIR__.'/../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
        }
    }
}
