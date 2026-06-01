<?php

namespace CleaniqueCoders\LaravelBilling\DataTransferObjects;

final class CheckoutIntent
{
    public function __construct(
        public string $redirectUrl,   // where to send the customer
        public string $externalId,    // echoed back by the webhook for correlation
    ) {}
}
