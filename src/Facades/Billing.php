<?php

namespace CleaniqueCoders\LaravelBilling\Facades;

use CleaniqueCoders\LaravelBilling\BillingManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \CleaniqueCoders\LaravelBilling\Contracts\PaymentGateway gateway(?string $name = null)
 * @method static \CleaniqueCoders\LaravelBilling\BillingManager extend(string $name, \Closure $callback)
 * @method static void handle(\CleaniqueCoders\LaravelBilling\DataTransferObjects\WebhookEvent $event)
 * @method static string getDefaultGateway()
 *
 * @see BillingManager
 */
class Billing extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'billing';
    }
}
