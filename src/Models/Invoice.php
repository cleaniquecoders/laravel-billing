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
 * @property int $subtotal_cents
 * @property int $tax_cents
 * @property float|null $tax_rate
 * @property string|null $tax_label
 * @property int $total_cents
 * @property string $currency
 * @property InvoiceStatus $status
 * @property Carbon|null $issued_at
 * @property Carbon|null $paid_at
 * @property string|null $storage_path
 * @property array<string,mixed> $metadata
 * @property-read Subscription|null $subscription
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
        'subtotal_cents' => 'integer',
        'tax_cents' => 'integer',
        'tax_rate' => 'float',
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

    public function subtotalMajor(): float
    {
        return $this->subtotal_cents / 100;
    }

    public function taxMajor(): float
    {
        return $this->tax_cents / 100;
    }

    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::Paid;
    }

    /**
     * The payment method shown on the invoice/receipt. Real gateways may store
     * a card brand + last4 in metadata; falls back to the subscription gateway.
     */
    public function paymentMethod(): ?string
    {
        $method = $this->metadata['payment_method'] ?? null;

        if (is_string($method) && $method !== '') {
            return $method;
        }

        return $this->subscription?->gateway;
    }

    /**
     * Invoice line items. Apps may store a richer breakdown in
     * metadata['line_items']; otherwise a single plan line is derived.
     *
     * @return array<int,array{description:string,qty:int,unit_cents:int,amount_cents:int}>
     */
    public function lineItems(): array
    {
        $items = $this->metadata['line_items'] ?? null;

        if (is_array($items) && $items !== []) {
            return $items;
        }

        return [[
            'description' => ucfirst($this->plan_tier).' plan ('.$this->interval->value.')',
            'qty' => 1,
            'unit_cents' => $this->subtotal_cents,
            'amount_cents' => $this->subtotal_cents,
        ]];
    }
}
