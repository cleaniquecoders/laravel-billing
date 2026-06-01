<?php

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Configures the workbench demo: a small plan matrix (config store), the local
 * gateway, SST tax, seller details, and the Flux-enabled demo layout. Mirrors
 * what a host app would set in config/billing.php.
 */
class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Point the package's public path at the package root so @vite finds
        // the dev server hot file / built assets under ./public.
        $this->app->usePublicPath(dirname(__DIR__, 3).'/public');

        config([
            'billing.store' => 'config',
            'billing.default' => 'local',
            'billing.default_plan' => 'free',
            'billing.currency' => 'MYR',

            'billing.gateways.local.enabled' => true,
            'billing.gateways.local.auto' => false, // show the dev "Approve" page

            'billing.tax' => ['enabled' => true, 'rate' => 0.08, 'label' => 'SST'],

            'billing.company' => [
                'name' => 'Cleanique Coders Resources',
                'ssm' => '201701234567',
                'sst' => 'W10-1234-56789012',
                'email' => 'billing@cleaniquecoders.com',
                'website' => 'https://cleaniquecoders.com',
                'address' => [
                    'street_1' => 'No. 244, Jalan Sentosa',
                    'street_2' => 'Taman Sentosa',
                    'postcode' => '72500',
                    'city' => 'Kuala Pilah',
                    'state' => 'Negeri Sembilan',
                    'country' => 'Malaysia',
                ],
            ],

            'billing.plans' => [
                'free' => [
                    'name' => 'Free',
                    'tagline' => 'Get started',
                    'price_cents' => ['monthly' => 0, 'annual' => 0],
                    'limits' => ['seats' => 1],
                    'features' => ['1 seat', 'Community support'],
                    'is_active' => true,
                    'sort_order' => 0,
                ],
                'pro' => [
                    'name' => 'Pro',
                    'tagline' => 'For growing teams',
                    'price_cents' => ['monthly' => 4900, 'annual' => 49000],
                    'limits' => ['seats' => 10],
                    'features' => ['10 seats', 'Priority support', 'Custom invoices'],
                    'is_active' => true,
                    'sort_order' => 1,
                ],
                'team' => [
                    'name' => 'Team',
                    'tagline' => 'For organisations',
                    'price_cents' => ['monthly' => 9900, 'annual' => 99000],
                    'limits' => ['seats' => 50],
                    'features' => ['50 seats', 'SSO', 'Dedicated support'],
                    'is_active' => true,
                    'sort_order' => 2,
                ],
            ],
        ]);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(dirname(__DIR__, 2).'/resources/views', 'workbench');

        config(['billing.layout' => 'workbench::layouts.billing']);
    }
}
