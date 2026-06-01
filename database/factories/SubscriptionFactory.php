<?php

namespace CleaniqueCoders\LaravelBilling\Database\Factories;

use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Enums\SubscriptionStatus;
use CleaniqueCoders\LaravelBilling\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'plan_tier' => 'free',
            'status' => SubscriptionStatus::Active,
            'interval' => PlanInterval::Monthly,
            'trial_ends_at' => null,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'canceled_at' => null,
            'cancel_at_period_end' => false,
            'gateway' => 'local',
            'gateway_subscription_id' => 'sub_'.$this->faker->unique()->numerify('########'),
        ];
    }

    public function incomplete(): static
    {
        return $this->state(fn () => ['status' => SubscriptionStatus::Incomplete]);
    }

    public function pastDue(): static
    {
        return $this->state(fn () => ['status' => SubscriptionStatus::PastDue]);
    }
}
