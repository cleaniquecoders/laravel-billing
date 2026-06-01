<?php

namespace CleaniqueCoders\LaravelBilling\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Incomplete = 'incomplete';

    /**
     * Whether a subscription in this status currently grants access.
     */
    public function grantsAccess(): bool
    {
        return match ($this) {
            self::Trialing, self::Active, self::PastDue => true,
            self::Canceled, self::Incomplete => false,
        };
    }
}
