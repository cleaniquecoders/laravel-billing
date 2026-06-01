<?php

namespace CleaniqueCoders\LaravelBilling\Support;

use CleaniqueCoders\LaravelBilling\Contracts\Billable;
use CleaniqueCoders\LaravelBilling\Models\Plan;
use CleaniqueCoders\LaravelBilling\Models\Subscription;
use CleaniqueCoders\LaravelBilling\Models\UsageCounter;
use CleaniqueCoders\LaravelBilling\Services\PlanRepository;

/**
 * Gates and records metered usage against the billable's active plan limits.
 * A null limit means unlimited.
 */
class PlanLimits
{
    public function __construct(protected PlanRepository $plans) {}

    /**
     * The configured limit for a meter on the billable's active plan.
     * Null means unlimited.
     */
    public function limit(Billable $billable, string $meter): ?int
    {
        return $this->planFor($billable)->limit($meter);
    }

    public function used(Billable $billable, string $meter, ?string $period = null): int
    {
        $counter = $this->counter($billable, $meter, $period ?? $this->currentPeriod(), create: false);

        return $counter === null ? 0 : (int) $counter->used;
    }

    /**
     * Remaining allowance. Null means unlimited.
     */
    public function remaining(Billable $billable, string $meter, ?string $period = null): ?int
    {
        $limit = $this->limit($billable, $meter);

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->used($billable, $meter, $period));
    }

    public function canConsume(Billable $billable, string $meter, int $amount = 1): bool
    {
        $limit = $this->limit($billable, $meter);

        if ($limit === null) {
            return true; // unlimited
        }

        return $this->used($billable, $meter) + $amount <= $limit;
    }

    public function record(Billable $billable, string $meter, int $amount = 1, ?string $period = null): void
    {
        $counter = $this->counter($billable, $meter, $period ?? $this->currentPeriod(), create: true);

        $counter->increment('used', $amount);
    }

    protected function counter(Billable $billable, string $meter, string $period, bool $create): ?UsageCounter
    {
        /** @var class-string<UsageCounter> $model */
        $model = config('billing.models.usage_counter', UsageCounter::class);

        $attributes = [
            'billable_type' => $billable->getMorphClass(),
            'billable_id' => $billable->getKey(),
            'meter' => $meter,
            'period' => $period,
        ];

        if ($create) {
            return $model::query()->firstOrCreate($attributes, ['used' => 0]);
        }

        return $model::query()->where($attributes)->first();
    }

    protected function planFor(Billable $billable): Plan
    {
        $subscription = $this->activeSubscription($billable);
        $tier = $subscription?->plan_tier;

        if ($tier !== null && ($plan = $this->plans->find($tier)) !== null) {
            return $plan;
        }

        return $this->plans->default();
    }

    protected function activeSubscription(Billable $billable): ?Subscription
    {
        /** @var class-string<Subscription> $model */
        $model = config('billing.models.subscription', Subscription::class);

        return $model::query()
            ->where('billable_type', $billable->getMorphClass())
            ->where('billable_id', $billable->getKey())
            ->latest('id')
            ->get()
            ->first(fn (Subscription $s) => $s->grantsAccess() || $s->onGracePeriod());
    }

    protected function currentPeriod(): string
    {
        return now()->format('Y-m');
    }
}
