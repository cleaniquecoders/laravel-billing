<?php

namespace CleaniqueCoders\LaravelBilling;

use CleaniqueCoders\LaravelBilling\Contracts\Billable;
use CleaniqueCoders\LaravelBilling\Contracts\PaymentGateway;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutIntent;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\WebhookEvent;
use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Enums\SubscriptionStatus;
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
use CleaniqueCoders\LaravelBilling\Gateways\LocalGateway;
use CleaniqueCoders\LaravelBilling\Models\Plan;
use CleaniqueCoders\LaravelBilling\Models\Subscription;
use CleaniqueCoders\LaravelBilling\Services\WebhookProcessor;
use Closure;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves the active PaymentGateway driver by name (Laravel Manager pattern)
 * and exposes the normalised webhook dispatcher.
 */
class BillingManager
{
    /** @var array<string,Closure> */
    protected array $customCreators = [];

    /** @var array<string,PaymentGateway> */
    protected array $resolved = [];

    public function __construct(protected Container $container) {}

    /**
     * Resolve a gateway driver. Defaults to config('billing.default').
     */
    public function gateway(?string $name = null): PaymentGateway
    {
        $name ??= $this->getDefaultGateway();

        return $this->resolved[$name] ??= $this->resolve($name);
    }

    /**
     * Register a driver factory at runtime.
     */
    public function extend(string $name, Closure $callback): static
    {
        $this->customCreators[$name] = $callback;
        unset($this->resolved[$name]);

        return $this;
    }

    /**
     * Begin a subscription checkout: create the pending (Incomplete)
     * subscription, delegate to the driver, and correlate the webhook by
     * externalId. When the gateway is configured with auto=true, the
     * activation is processed immediately (CI/tests/demo).
     */
    public function checkout(
        Billable $billable,
        Plan $plan,
        PlanInterval $interval,
        string $returnUrl,
        ?string $gateway = null,
    ): CheckoutIntent {
        $name = $gateway ?? $this->getDefaultGateway();
        $driver = $this->gateway($name);

        $intent = $driver->createCheckout($billable, $plan, $interval, $returnUrl);

        /** @var class-string<Subscription> $model */
        $model = config('billing.models.subscription', Subscription::class);
        $now = now();

        /** @var Subscription $subscription */
        $subscription = new $model([
            'billable_type' => $billable->getMorphClass(),
            'billable_id' => $billable->getKey(),
            'plan_tier' => $plan->tier,
            'status' => SubscriptionStatus::Incomplete,
            'interval' => $interval,
            'current_period_start' => $now,
            'current_period_end' => $now->copy()->addMonths($interval->months()),
            'cancel_at_period_end' => false,
            'gateway' => $name,
            'gateway_subscription_id' => $intent->externalId,
        ]);
        $subscription->save();

        if (config("billing.gateways.{$name}.auto")) {
            $this->handle(new WebhookEvent(
                type: WebhookEventType::SubscriptionActivated,
                externalId: $intent->externalId,
                amountCents: $plan->priceCents($interval),
                providerEventId: 'auto-'.$intent->externalId,
            ));
        }

        return $intent;
    }

    /**
     * Process a normalised inbound webhook event: replay-guard, locate the
     * subscription, transition status, issue invoices, fire events.
     */
    public function handle(WebhookEvent $event): void
    {
        $this->container->make(WebhookProcessor::class)->process($event);
    }

    public function getDefaultGateway(): string
    {
        return config('billing.default', 'local');
    }

    protected function resolve(string $name): PaymentGateway
    {
        if (isset($this->customCreators[$name])) {
            return $this->build($this->customCreators[$name]($this->container, $name), $name);
        }

        $config = config("billing.gateways.{$name}");

        if ($config === null) {
            throw new InvalidArgumentException("Billing gateway [{$name}] is not configured.");
        }

        $driver = $config['driver'] ?? null;

        if ($driver === 'local') {
            return $this->build($this->container->make(LocalGateway::class), $name);
        }

        if (is_string($driver) && class_exists($driver)) {
            return $this->build($this->container->make($driver), $name);
        }

        throw new InvalidArgumentException("Billing gateway [{$name}] has an invalid driver.");
    }

    protected function build(mixed $instance, string $name): PaymentGateway
    {
        if (! $instance instanceof PaymentGateway) {
            throw new InvalidArgumentException(
                "Billing gateway [{$name}] must implement ".PaymentGateway::class.'.'
            );
        }

        return $instance;
    }
}
