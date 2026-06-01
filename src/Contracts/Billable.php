<?php

namespace CleaniqueCoders\LaravelBilling\Contracts;

use CleaniqueCoders\LaravelBilling\Models\Plan;
use CleaniqueCoders\LaravelBilling\Models\Subscription;
use Illuminate\Database\Eloquent\Relations\MorphMany;

interface Billable
{
    /**
     * The polymorphic billable_type value.
     *
     * Declared without a return type to stay compatible with Eloquent's
     * Model::getMorphClass(), which any billable model already provides.
     */
    public function getMorphClass();

    /** The polymorphic billable_id value. */
    public function getKey();

    /** Where invoices are sent. */
    public function billingEmail(): string;

    /** Shown on the invoice "Bill to". */
    public function billingName(): string;

    /**
     * Address lines shown on the invoice.
     *
     * @return array<string,string>
     */
    public function billingAddress(): array;

    /**
     * Subscriptions belonging to this billable. Provided by HasSubscriptions.
     */
    public function subscriptions(): MorphMany;

    /**
     * The current access-granting subscription, or null. Provided by
     * HasSubscriptions.
     */
    public function subscription(): ?Subscription;

    /**
     * Invoices belonging to this billable. Provided by HasSubscriptions.
     */
    public function invoices(): MorphMany;

    /**
     * The active plan, or the configured default/free plan. Provided by
     * HasSubscriptions.
     */
    public function plan(): Plan;
}
