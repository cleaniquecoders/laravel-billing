<?php

namespace CleaniqueCoders\LaravelBilling\Enums;

enum PlanInterval: string
{
    case Monthly = 'monthly';
    case Annual = 'annual';

    /**
     * The number of months one billing period of this interval spans.
     */
    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Annual => 12,
        };
    }
}
