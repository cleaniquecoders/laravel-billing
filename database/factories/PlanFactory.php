<?php

namespace CleaniqueCoders\LaravelBilling\Database\Factories;

use CleaniqueCoders\LaravelBilling\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $tier = $this->faker->unique()->slug(1);

        return [
            'tier' => $tier,
            'name' => ucfirst($tier),
            'tagline' => null,
            'price_cents' => ['monthly' => 1990, 'annual' => 19900],
            'limits' => ['seats' => 5],
            'features' => [],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => [
            'tier' => 'free',
            'name' => 'Free',
            'price_cents' => ['monthly' => 0, 'annual' => 0],
            'limits' => ['seats' => 1],
        ]);
    }
}
