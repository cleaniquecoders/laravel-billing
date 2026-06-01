<?php

namespace CleaniqueCoders\LaravelBilling\Models;

use CleaniqueCoders\LaravelBilling\Enums\InvoiceStatus;
use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\Traitify\Concerns\InteractsWithUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $number
 * @property string $billable_type
 * @property int|string $billable_id
 * @property int|null $subscription_id
 * @property string $plan_tier
 * @property PlanInterval $interval
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property int $total_cents
 * @property string $currency
 * @property InvoiceStatus $status
 * @property Carbon|null $issued_at
 * @property Carbon|null $paid_at
 * @property string|null $storage_path
 * @property array<string,mixed> $metadata
 */
class Invoice extends Model
{
    use InteractsWithUuid;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'status' => InvoiceStatus::class,
        'interval' => PlanInterval::class,
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'issued_at' => 'datetime',
        'paid_at' => 'datetime',
        'total_cents' => 'integer',
        'metadata' => 'array',
    ];

    public function getTable()
    {
        return config('billing.tables.invoices', 'invoices');
    }

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(config('billing.models.subscription', Subscription::class));
    }

    public function totalMajor(): float
    {
        return $this->total_cents / 100;
    }
}
