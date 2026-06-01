<?php

namespace CleaniqueCoders\LaravelBilling\Events;

use CleaniqueCoders\LaravelBilling\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public ?int $amountCents = null,
    ) {}
}
