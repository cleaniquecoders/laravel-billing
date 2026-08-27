<?php

namespace CleaniqueCoders\LaravelBilling\DataTransferObjects;

/**
 * One payment to collect, described without reference to a Plan, a
 * Subscription, or any other model in this package.
 *
 * ## Why this exists
 *
 * Every bundled driver except Stripe already creates a **one-off bill** —
 * Billplz posts to `/bills`, toyyibPay to `createBill`, Bayarcash to
 * `/payment-intents`. The plan and the interval were only ever used to derive
 * four values: an amount, a description, who is paying, and where to send them
 * back. Typing the contract on `Plan` and `Billable` therefore coupled ten
 * drivers to this package's subscription tables for no reason, and made them
 * unusable for the other thing a host application always eventually needs —
 * charging for an invoice, a top-up, a one-time fee.
 *
 * So the one-off charge is now the **primitive**, and a plan checkout is a
 * one-off charge described by a plan. `Gateway::createCheckout()` does that
 * mapping once, in the base class, and every driver keeps working unchanged.
 *
 * ## The reference belongs to the caller
 *
 * Drivers used to mint their own order id (`SUB` + random). That is fine when
 * the only caller is this package's subscription flow and wrong for everyone
 * else: an application charging invoice #1234 needs the gateway to echo back
 * something it can find #1234 from. Pass one, or leave it null and the driver
 * mints one as before.
 */
final class CheckoutRequest
{
    /**
     * @param  int  $amountCents  minor units — never a float. Money in a float
     *                            is a rounding error waiting for a reconciliation.
     * @param  ?string  $reference  the caller's own correlation id, echoed back by
     *                              the gateway where the gateway allows it. Null lets the
     *                              driver mint one.
     * @param  ?string  $callbackUrl  overrides the gateway's configured callback,
     *                                for an application that routes webhooks per tenant
     * @param  array<string,mixed>  $metadata  passed through where the gateway
     *                                         supports it; ignored where it does not
     */
    public function __construct(
        public int $amountCents,
        public string $description,
        public string $customerName,
        public string $customerEmail,
        public string $returnUrl,
        public ?string $reference = null,
        public string $currency = 'MYR',
        public ?string $customerPhone = null,
        public ?string $callbackUrl = null,
        public array $metadata = [],
    ) {}

    /** The amount as a decimal string, which is what most Malaysian gateways post. */
    public function amountDecimal(): string
    {
        return number_format($this->amountCents / 100, 2, '.', '');
    }
}
