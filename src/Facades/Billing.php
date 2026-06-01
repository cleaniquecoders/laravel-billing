<?php

namespace CleaniqueCoders\LaravelBilling\Facades;

use CleaniqueCoders\LaravelBilling\BillingManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \CleaniqueCoders\LaravelBilling\Contracts\PaymentGateway gateway(?string $name = null)
 * @method static \CleaniqueCoders\LaravelBilling\BillingManager extend(string $name, \Closure $callback)
 * @method static \CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutIntent checkout(\CleaniqueCoders\LaravelBilling\Contracts\Billable $billable, \CleaniqueCoders\LaravelBilling\Models\Plan $plan, \CleaniqueCoders\LaravelBilling\Enums\PlanInterval $interval, string $returnUrl, ?string $gateway = null)
 * @method static void handle(\CleaniqueCoders\LaravelBilling\DataTransferObjects\WebhookEvent $event)
 * @method static \CleaniqueCoders\LaravelBilling\Models\Subscription cancel(\CleaniqueCoders\LaravelBilling\Models\Subscription $subscription, bool $atPeriodEnd = true)
 * @method static \CleaniqueCoders\LaravelBilling\Models\Subscription resume(\CleaniqueCoders\LaravelBilling\Models\Subscription $subscription)
 * @method static \CleaniqueCoders\LaravelBilling\Models\Subscription swap(\CleaniqueCoders\LaravelBilling\Models\Subscription $subscription, \CleaniqueCoders\LaravelBilling\Models\Plan $plan, \CleaniqueCoders\LaravelBilling\Enums\PlanInterval $interval)
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
