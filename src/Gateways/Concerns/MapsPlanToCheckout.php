<?php

namespace CleaniqueCoders\LaravelBilling\Gateways\Concerns;

use CleaniqueCoders\LaravelBilling\Contracts\Billable;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutIntent;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutRequest;
use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Models\Plan;

/**
 * A plan checkout IS a one-off charge, described by a plan.
 *
 * One copy of the mapping, used by the `Gateway` base class and by
 * `LocalGateway` — which implements the contract directly rather than
 * extending the base, and would otherwise carry a second copy that drifts.
 *
 * A driver overrides `createCheckout()` only when the gateway has a genuinely
 * different subscription mode: of the bundled ten, Stripe and PayPal do, and
 * both keep their own path because a native subscription and an ad-hoc charge
 * are different objects at the vendor, not two spellings of one.
 */
trait MapsPlanToCheckout
{
    public function createCheckout(
        Billable $billable,
        Plan $plan,
        PlanInterval $interval,
        string $returnUrl,
    ): CheckoutIntent {
        return $this->checkout(new CheckoutRequest(
            amountCents: $plan->priceCents($interval),
            description: $plan->name.' ('.$interval->value.')',
            customerName: $billable->billingName(),
            customerEmail: $billable->billingEmail(),
            returnUrl: $returnUrl,
            currency: (string) config('billing.currency', 'MYR'),
            metadata: [
                'billable_type' => $billable->getMorphClass(),
                'billable_id' => (string) $billable->getKey(),
                'plan' => $plan->tier,
                'interval' => $interval->value,
            ],
        ));
    }
}
