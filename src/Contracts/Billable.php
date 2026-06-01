<?php

namespace CleaniqueCoders\LaravelBilling\Contracts;

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
}
