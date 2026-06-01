<?php

namespace CleaniqueCoders\LaravelBilling\Services;

use CleaniqueCoders\LaravelBilling\DataTransferObjects\WebhookEvent;
use CleaniqueCoders\LaravelBilling\Enums\SubscriptionStatus;
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
use CleaniqueCoders\LaravelBilling\Events;
use CleaniqueCoders\LaravelBilling\Models\Subscription;
use Illuminate\Support\Facades\Cache;

/**
 * Normalised inbound webhook dispatcher: replay-guards on providerEventId,
 * locates the subscription, transitions status, issues invoices on
 * activate/renew, and fires the matching event.
 */
class WebhookProcessor
{
    public function process(WebhookEvent $event): void
    {
        if ($this->isReplay($event)) {
            return;
        }

        $subscription = $this->locate($event->externalId);

        if ($subscription === null) {
            return;
        }

        match ($event->type) {
            WebhookEventType::SubscriptionActivated => $this->activate($subscription),
            WebhookEventType::SubscriptionRenewed => $this->renew($subscription),
            WebhookEventType::PaymentSucceeded => $this->paymentSucceeded($subscription, $event),
            WebhookEventType::PaymentFailed => $this->paymentFailed($subscription, $event),
            WebhookEventType::SubscriptionCanceled => $this->cancel($subscription),
        };
    }

    protected function activate(Subscription $subscription): void
    {
        $start = now();

        $subscription->forceFill([
            'status' => SubscriptionStatus::Active,
            'current_period_start' => $start,
            'current_period_end' => $start->copy()->addMonths($subscription->interval->months()),
            'canceled_at' => null,
            'cancel_at_period_end' => false,
        ])->save();

        app(IssueInvoice::class)($subscription, email: true);

        Events\SubscriptionActivated::dispatch($subscription);
    }

    protected function renew(Subscription $subscription): void
    {
        $base = $subscription->current_period_end?->isFuture()
            ? $subscription->current_period_end->copy()
            : now();

        $subscription->forceFill([
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now(),
            'current_period_end' => $base->addMonths($subscription->interval->months()),
        ])->save();

        app(IssueInvoice::class)($subscription, email: true);

        Events\SubscriptionRenewed::dispatch($subscription);
    }

    protected function paymentSucceeded(Subscription $subscription, WebhookEvent $event): void
    {
        if ($subscription->status === SubscriptionStatus::PastDue) {
            $subscription->forceFill(['status' => SubscriptionStatus::Active])->save();
        }

        Events\PaymentSucceeded::dispatch($subscription, $event->amountCents);
    }

    protected function paymentFailed(Subscription $subscription, WebhookEvent $event): void
    {
        $subscription->forceFill(['status' => SubscriptionStatus::PastDue])->save();

        Events\PaymentFailed::dispatch($subscription, $event->amountCents);
    }

    protected function cancel(Subscription $subscription): void
    {
        $subscription->forceFill([
            'status' => SubscriptionStatus::Canceled,
            'canceled_at' => now(),
        ])->save();

        Events\SubscriptionCanceled::dispatch($subscription);
    }

    protected function locate(string $externalId): ?Subscription
    {
        /** @var class-string<Subscription> $model */
        $model = config('billing.models.subscription', Subscription::class);

        return $model::query()->where('gateway_subscription_id', $externalId)->first()
            ?? $model::query()->where('uuid', $externalId)->first();
    }

    /**
     * Replay guard. Returns true when this providerEventId was already seen.
     */
    protected function isReplay(WebhookEvent $event): bool
    {
        if ($event->providerEventId === null) {
            return false;
        }

        $key = 'billing:webhook:'.$event->providerEventId;
        $ttl = (int) config('billing.webhook.replay_ttl', 60 * 60 * 24 * 30);

        // Cache::add returns false when the key already exists → replay.
        return Cache::add($key, true, $ttl) === false;
    }
}
