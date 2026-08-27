<?php

namespace CleaniqueCoders\LaravelBilling\Tests\Fixtures\Gateways;

use CleaniqueCoders\LaravelBilling\Contracts\Billable;
use CleaniqueCoders\LaravelBilling\Contracts\PaymentGateway;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutIntent;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutRequest;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\PaymentStatus;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\WebhookEvent;
use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
use CleaniqueCoders\LaravelBilling\Models\Plan;
use CleaniqueCoders\LaravelBilling\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * In-memory test double. Records calls and lets tests craft raw webhook
 * payloads without any HTTP/signature machinery.
 */
class ArrayGateway implements PaymentGateway
{
    /** @var array<int,array<string,mixed>> */
    public array $checkouts = [];

    /** @var array<int,Subscription> */
    public array $canceled = [];

    /** @var array<int,CheckoutRequest> */
    public array $charges = [];

    /** @var array<string,PaymentStatus> */
    public array $statuses = [];

    public function createCheckout(
        Billable $billable,
        Plan $plan,
        PlanInterval $interval,
        string $returnUrl,
    ): CheckoutIntent {
        $externalId = 'arr_'.Str::random(12);

        $this->checkouts[] = [
            'external_id' => $externalId,
            'plan' => $plan->tier,
            'interval' => $interval->value,
            'return_url' => $returnUrl,
        ];

        return new CheckoutIntent($returnUrl, $externalId);
    }

    public function checkout(CheckoutRequest $request): CheckoutIntent
    {
        $this->charges[] = $request;

        $externalId = $request->reference ?? 'arr_'.Str::random(12);

        return new CheckoutIntent($request->returnUrl, $externalId);
    }

    public function fetch(string $externalId): ?PaymentStatus
    {
        return $this->statuses[$externalId] ?? null;
    }

    public function cancel(Subscription $subscription): void
    {
        $this->canceled[] = $subscription;
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        if ($request->input('signature') !== 'valid') {
            return null;
        }

        return new WebhookEvent(
            type: WebhookEventType::from($request->input('type')),
            externalId: (string) $request->input('external_id'),
            amountCents: $request->input('amount_cents'),
            providerEventId: $request->input('event_id'),
            rawPayload: $request->all(),
        );
    }
}
