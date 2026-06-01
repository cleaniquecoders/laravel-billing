<?php

namespace CleaniqueCoders\LaravelBilling\Contracts;

use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutIntent;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\WebhookEvent;
use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Models\Plan;
use CleaniqueCoders\LaravelBilling\Models\Subscription;
use Illuminate\Http\Request;

/**
 * The single extension point. Apps implement this for real gateways
 * (BayarCash, ToyyibPay, Chip, Stripe…). The package never names one.
 */
interface PaymentGateway
{
    /**
     * Begin a checkout; return where to send the customer + an id to
     * correlate the inbound webhook.
     */
    public function createCheckout(
        Billable $billable,
        Plan $plan,
        PlanInterval $interval,
        string $returnUrl,
    ): CheckoutIntent;

    /**
     * Cancel/terminate the upstream subscription (DD enrollment, etc.).
     */
    public function cancel(Subscription $subscription): void;

    /**
     * Verify signature & normalise an inbound callback. Return null if the
     * payload is invalid.
     */
    public function parseWebhook(Request $request): ?WebhookEvent;
}
