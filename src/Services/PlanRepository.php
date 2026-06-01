<?php

namespace CleaniqueCoders\LaravelBilling\Services;

use CleaniqueCoders\LaravelBilling\Models\Plan;
use Illuminate\Support\Collection;

/**
 * Resolves plans from both config and the database. Config is the canonical
 * seed source; the active store decides where reads come from.
 */
class PlanRepository
{
    /**
     * @return Collection<int,Plan>
     */
    public function all(): Collection
    {
        if ($this->usesDatabase()) {
            return $this->planModel()::query()
                ->orderBy('sort_order')
                ->get();
        }

        return collect(config('billing.plans', []))
            ->map(fn (array $attributes, string $tier) => $this->makePlan($tier, $attributes))
            ->sortBy('sort_order')
            ->values();
    }

    public function find(string $tier): ?Plan
    {
        if ($this->usesDatabase()) {
            return $this->planModel()::query()->where('tier', $tier)->first();
        }

        $attributes = config("billing.plans.{$tier}");

        return $attributes === null ? null : $this->makePlan($tier, $attributes);
    }

    public function default(): Plan
    {
        $tier = config('billing.default_plan', 'free');

        return $this->find($tier)
            ?? $this->makePlan($tier, [
                'name' => ucfirst($tier),
                'price_cents' => ['monthly' => 0, 'annual' => 0],
                'limits' => [],
                'features' => [],
                'is_active' => true,
                'sort_order' => 0,
            ]);
    }

    protected function usesDatabase(): bool
    {
        return config('billing.store', 'database') === 'database';
    }

    /**
     * Build a non-persisted Plan instance from a config array.
     *
     * @param  array<string,mixed>  $attributes
     */
    protected function makePlan(string $tier, array $attributes): Plan
    {
        $class = $this->planModel();

        /** @var Plan $plan */
        $plan = new $class([
            'tier' => $tier,
            'name' => $attributes['name'] ?? ucfirst($tier),
            'tagline' => $attributes['tagline'] ?? null,
            'price_cents' => $attributes['price_cents'] ?? ['monthly' => 0, 'annual' => 0],
            'limits' => $attributes['limits'] ?? [],
            'features' => $attributes['features'] ?? [],
            'is_active' => $attributes['is_active'] ?? true,
            'sort_order' => $attributes['sort_order'] ?? 0,
        ]);

        $plan->exists = false;

        return $plan;
    }

    /**
     * @return class-string<Plan>
     */
    protected function planModel(): string
    {
        return config('billing.models.plan', Plan::class);
    }
}
