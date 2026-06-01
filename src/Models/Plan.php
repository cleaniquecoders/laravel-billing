<?php

namespace CleaniqueCoders\LaravelBilling\Models;

use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\Traitify\Concerns\InteractsWithUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tier
 * @property string $name
 * @property string|null $tagline
 * @property array<string,int> $price_cents
 * @property array<string,int|null> $limits
 * @property array<int,string> $features
 * @property bool $is_active
 * @property int $sort_order
 */
class Plan extends Model
{
    use HasFactory;
    use InteractsWithUuid;

    protected $guarded = [];

    protected $casts = [
        'price_cents' => 'array',
        'limits' => 'array',
        'features' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function getTable()
    {
        return config('billing.tables.plans', 'plans');
    }

    /**
     * Price in cents for the given interval.
     */
    public function priceCents(PlanInterval $interval): int
    {
        return (int) ($this->price_cents[$interval->value] ?? 0);
    }

    /**
     * The configured limit for a meter. Null means unlimited.
     */
    public function limit(string $meter): ?int
    {
        $limits = $this->limits ?? [];

        if (! array_key_exists($meter, $limits)) {
            return null;
        }

        $value = $limits[$meter];

        return $value === null ? null : (int) $value;
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }

    public function isFree(): bool
    {
        return $this->priceCents(PlanInterval::Monthly) === 0
            && $this->priceCents(PlanInterval::Annual) === 0;
    }
}
