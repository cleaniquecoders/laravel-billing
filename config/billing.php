<?php

use CleaniqueCoders\LaravelBilling\Models\Invoice;
use CleaniqueCoders\LaravelBilling\Models\InvoiceSequence;
use CleaniqueCoders\LaravelBilling\Models\Plan;
use CleaniqueCoders\LaravelBilling\Models\Subscription;
use CleaniqueCoders\LaravelBilling\Models\UsageCounter;

// config for CleaniqueCoders/LaravelBilling
return [

    /*
    |--------------------------------------------------------------------------
    | Gateway selection
    |--------------------------------------------------------------------------
    |
    | The default gateway driver name (must be a key under "gateways"). Apps
    | implement the PaymentGateway contract and register their driver class
    | here. The bundled "local" driver works out of the box.
    |
    */

    'default' => env('BILLING_GATEWAY', 'local'),

    'gateways' => [
        'local' => [
            'driver' => 'local',
            'enabled' => env('BILLING_LOCAL_ENABLED', true), // never serves in production
            'auto' => env('BILLING_LOCAL_AUTO', false),      // auto-approve (CI/tests)
        ],
        // Apps add their own, e.g.
        // 'bayarcash' => ['driver' => App\Billing\BayarCashGateway::class],
        // 'toyyibpay' => ['driver' => App\Billing\ToyyibPayGateway::class],
    ],

    /*
    |--------------------------------------------------------------------------
    | Plan storage
    |--------------------------------------------------------------------------
    |
    | "database" reads plans from the plans table (hydrate via the publishable
    | seeder). "config" builds read-only Plan instances on the fly from the
    | array below — no plans table needed.
    |
    */

    'store' => env('BILLING_PLAN_STORE', 'database'), // 'database' | 'config'

    'default_plan' => env('BILLING_DEFAULT_PLAN', 'free'),

    'plans' => [
        'free' => [
            'name' => 'Free',
            'tagline' => null,
            'price_cents' => ['monthly' => 0, 'annual' => 0],
            'limits' => ['seats' => 1],
            'features' => [],
            'is_active' => true,
            'sort_order' => 0,
        ],
        // … app defines its own matrix
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoicing
    |--------------------------------------------------------------------------
    */

    'currency' => env('BILLING_CURRENCY', 'MYR'),

    'invoice' => [
        'prefix' => env('BILLING_INVOICE_PREFIX', 'INV'),
        'number_pad' => 6,
        'disk' => env('BILLING_INVOICE_DISK', 'local'),
        'path' => 'billing/{billable_type}/{billable_id}/invoices/{invoice_uuid}.pdf',
        'view' => 'billing::invoice-pdf',
    ],

    /*
    |--------------------------------------------------------------------------
    | Seller details on the invoice (neutral defaults)
    |--------------------------------------------------------------------------
    */

    'company' => [
        'name' => env('BILLING_COMPANY_NAME'),
        'ssm' => env('BILLING_COMPANY_SSM'),
        'sst' => env('BILLING_COMPANY_SST'),
        'email' => env('BILLING_COMPANY_EMAIL'),
        'website' => env('BILLING_COMPANY_WEBSITE'),
        'address' => [
            'street_1' => env('BILLING_COMPANY_STREET_1'),
            'street_2' => env('BILLING_COMPANY_STREET_2'),
            'postcode' => env('BILLING_COMPANY_POSTCODE'),
            'city' => env('BILLING_COMPANY_CITY'),
            'state' => env('BILLING_COMPANY_STATE'),
            'country' => env('BILLING_COMPANY_COUNTRY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscription behaviour
    |--------------------------------------------------------------------------
    |
    | When cancel_at_period_end is set, access continues until
    | current_period_end (grace period).
    |
    */

    'trial_days' => env('BILLING_TRIAL_DAYS', 0),

    /*
    |--------------------------------------------------------------------------
    | Model & audit behaviour
    |--------------------------------------------------------------------------
    |
    | Apps may override the engine models. The package ships its own
    | lightweight uuid + audit behaviour, opt-in here (dependency-light).
    |
    */

    'uuid' => env('BILLING_UUID', true),
    'audit' => env('BILLING_AUDIT', false), // populate created_by / updated_by

    'models' => [
        'subscription' => Subscription::class,
        'invoice' => Invoice::class,
        'plan' => Plan::class,
        'usage_counter' => UsageCounter::class,
        'invoice_sequence' => InvoiceSequence::class,
    ],
];
