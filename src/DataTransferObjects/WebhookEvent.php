<?php

namespace CleaniqueCoders\LaravelBilling\DataTransferObjects;

use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;

final class WebhookEvent
{
    /**
     * @param  array<string,mixed>  $rawPayload
     */
    public function __construct(
        public WebhookEventType $type,
        public string $externalId,
        public ?int $amountCents = null,
        public ?string $providerEventId = null,   // for replay dedup
        public array $rawPayload = [],
    ) {}
}
