<?php

namespace CleaniqueCoders\LaravelBilling\Models;

use CleaniqueCoders\LaravelBilling\Concerns\InteractsWithAudit;
use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Enums\SubscriptionStatus;
use CleaniqueCoders\LaravelBilling\Services\PlanRepository;
use CleaniqueCoders\Traitify\Concerns\InteractsWithUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $billable_type
 * @property int|string $billable_id
 * @property string $plan_tier
 * @property SubscriptionStatus $status
 * @property PlanInterval $interval
 * @property string $gateway
 * @property string|null $gateway_subscription_id
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property Carbon|null $canceled_at
 * @property bool $cancel_at_period_end
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class Subscription extends Model
{
    use HasFactory;
    use InteractsWithAudit;
    use InteractsWithUuid;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'status' => SubscriptionStatus::class,
        'interval' => PlanInterval::class,
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
        'cancel_at_period_end' => 'boolean',
    ];

    public function getTable()
    {
        return config('billing.tables.subscriptions', 'subscriptions');
    }

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The plan this subscription is on (resolved from the tier snapshot).
     */
    public function plan(): ?Plan
    {
        return app(PlanRepository::class)->find($this->plan_tier);
    }

    public function grantsAccess(): bool
    {
        return $this->status->grantsAccess();
    }

    public function onTrial(): bool
    {
        return $this->status === SubscriptionStatus::Trialing
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    /**
     * On grace period when set to cancel at period end but the period has not
     * yet elapsed — access continues until current_period_end.
     */
    public function onGracePeriod(): bool
    {
        return $this->cancel_at_period_end
            && $this->current_period_end !== null
            && $this->current_period_end->isFuture();
    }

    public function isCanceled(): bool
    {
        return $this->status === SubscriptionStatus::Canceled;
    }
}
