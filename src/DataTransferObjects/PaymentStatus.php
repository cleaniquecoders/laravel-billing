<?php

namespace CleaniqueCoders\LaravelBilling\DataTransferObjects;

/**
 * What the gateway says about one payment, asked directly rather than waited
 * for.
 *
 * A webhook is the only signal a driver gets by default, and a webhook that
 * was never delivered is indistinguishable from a payment that never
 * happened — which is the state a customer is in when they say "I paid" and
 * the application says otherwise. `PaymentGateway::fetch()` is how that
 * argument gets settled.
 *
 * `$paid` is deliberately separate from `$status`: the raw status strings are
 * gateway-specific (`paid`, `succeeded`, `complete`, `1`) and a caller should
 * not have to learn ten vocabularies to answer one question.
 */
final class PaymentStatus
{
    /**
     * @param  array<string,mixed>  $raw  the gateway's own payload, for the cases
     *                                    a normalised shape cannot answer
     */
    public function __construct(
        public string $externalId,
        public bool $paid,
        public string $status,
        public ?int $amountCents = null,
        public ?string $currency = null,
        public array $raw = [],
    ) {}
}
