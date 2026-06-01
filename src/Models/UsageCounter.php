<?php

namespace CleaniqueCoders\LaravelBilling\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $billable_type
 * @property int|string $billable_id
 * @property string $meter
 * @property string $period
 * @property int $used
 */
class UsageCounter extends Model
{
    protected $guarded = [];

    protected $casts = [
        'used' => 'integer',
    ];

    public function getTable()
    {
        return config('billing.tables.usage_counters', 'usage_counters');
    }

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }
}
