<?php

namespace CleaniqueCoders\LaravelBilling\Contracts;

use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutIntent;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutRequest;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\PaymentStatus;
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
     * Collect one payment, described without any model from this package.
     *
     * **The primitive.** Every bundled driver except Stripe already creates a
     * one-off bill, and the plan was only ever used to derive an amount, a
     * description and a payer — so this is what they actually do, stated
     * directly. It is also the method a host application needs to charge for
     * anything that is not a subscription: an invoice, a top-up, a one-time
     * fee.
     */
    public function checkout(CheckoutRequest $request): CheckoutIntent;

    /**
     * Begin a plan checkout; return where to send the customer + an id to
     * correlate the inbound webhook.
     *
     * Implemented once in `Gateways\Gateway` by mapping the plan onto a
     * `CheckoutRequest` and calling `checkout()`. A driver overrides it only
     * when the gateway has a genuinely different subscription mode — Stripe
     * is the one that does.
     */
    public function createCheckout(
        Billable $billable,
        Plan $plan,
        PlanInterval $interval,
        string $returnUrl,
    ): CheckoutIntent;

    /**
     * Ask the gateway about a payment instead of waiting to be told.
     *
     * Returns null when the gateway has no record of it, and **throws**
     * `UnsupportedByGateway` when the gateway offers no way to ask — those
     * are different facts, and a webhook that was never delivered is
     * otherwise indistinguishable from a payment that never happened.
     */
    public function fetch(string $externalId): ?PaymentStatus;

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
