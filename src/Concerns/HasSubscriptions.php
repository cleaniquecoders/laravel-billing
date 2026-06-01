<?php

namespace CleaniqueCoders\LaravelBilling\Concerns;

use CleaniqueCoders\LaravelBilling\Models\Invoice;
use CleaniqueCoders\LaravelBilling\Models\Plan;
use CleaniqueCoders\LaravelBilling\Models\Subscription;
use CleaniqueCoders\LaravelBilling\Models\UsageCounter;
use CleaniqueCoders\LaravelBilling\Services\PlanRepository;
use CleaniqueCoders\LaravelBilling\Support\PlanLimits;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Implements the Billable behaviour. Attach to any model (User, Team,
 * Workspace…) together with the Billable contract.
 *
 * @mixin Model
 */
trait HasSubscriptions
{
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(
            config('billing.models.subscription', Subscription::class),
            'billable'
        )->latest('id');
    }

    public function invoices(): MorphMany
    {
        return $this->morphMany(
            config('billing.models.invoice', Invoice::class),
            'billable'
        )->latest('issued_at');
    }

    /**
     * The current access-granting subscription, if any.
     */
    public function subscription(): ?Subscription
    {
        return $this->subscriptions()
            ->get()
            ->first(fn (Subscription $subscription) => $subscription->grantsAccess() || $subscription->onGracePeriod());
    }

    public function subscribedTo(string $tier): bool
    {
        $subscription = $this->subscription();

        return $subscription !== null && $subscription->plan_tier === $tier;
    }

    public function onTrial(): bool
    {
        return (bool) $this->subscription()?->onTrial();
    }

    public function onGracePeriod(): bool
    {
        return (bool) $this->subscription()?->onGracePeriod();
    }

    /**
     * The active plan, or the configured default/free plan.
     */
    public function plan(): Plan
    {
        $repository = app(PlanRepository::class);
        $tier = $this->subscription()?->plan_tier;

        if ($tier !== null && ($plan = $repository->find($tier)) !== null) {
            return $plan;
        }

        return $repository->default();
    }

    public function canConsume(string $meter, int $amount = 1): bool
    {
        return app(PlanLimits::class)->canConsume($this, $meter, $amount);
    }

    public function recordUsage(string $meter, int $amount = 1): void
    {
        app(PlanLimits::class)->record($this, $meter, $amount);
    }

    /**
     * Usage counters for this billable.
     */
    public function usageCounters(): MorphMany
    {
        return $this->morphMany(
            config('billing.models.usage_counter', UsageCounter::class),
            'billable'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Billable contract defaults (override per app as needed)
    |--------------------------------------------------------------------------
    */

    public function billingEmail(): string
    {
        return (string) ($this->email ?? '');
    }

    public function billingName(): string
    {
        return (string) ($this->name ?? $this->billingEmail());
    }

    /**
     * @return array<string,string>
     */
    public function billingAddress(): array
    {
        return [];
    }
}
