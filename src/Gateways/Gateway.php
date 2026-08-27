<?php

namespace CleaniqueCoders\LaravelBilling\Gateways;

use CleaniqueCoders\LaravelBilling\Contracts\PaymentGateway;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutRequest;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\PaymentStatus;
use CleaniqueCoders\LaravelBilling\Exceptions\UnsupportedByGateway;
use CleaniqueCoders\LaravelBilling\Gateways\Concerns\MapsPlanToCheckout;
use CleaniqueCoders\LaravelBilling\Gateways\Concerns\SignsPayloads;
use Illuminate\Support\Str;

/**
 * Base class for the bundled real gateway drivers. Holds the gateway's config
 * block (injected by BillingManager::resolve()) and the SignsPayloads helpers.
 * Concrete drivers implement the three PaymentGateway methods.
 */
abstract class Gateway implements PaymentGateway
{
    use MapsPlanToCheckout;
    use SignsPayloads;

    /**
     * @param  array<string,mixed>  $config  the config('billing.gateways.<name>') block
     */
    public function __construct(protected array $config) {}

    /**
     * Most gateways offer no way to ask about a payment, so the honest default
     * is to say so rather than to answer null — which already means "asked,
     * and there is nothing there".
     */
    public function fetch(string $externalId): ?PaymentStatus
    {
        throw UnsupportedByGateway::cannot(static::class, 'be asked about a payment');
    }

    /**
     * A reference the caller supplied, or a fresh one.
     *
     * Drivers used to mint their own unconditionally, which is correct when
     * the only caller is this package's subscription flow and wrong for
     * everyone else: an application charging invoice #1234 needs the gateway
     * to echo back something it can find #1234 from.
     */
    protected function reference(CheckoutRequest $request, string $prefix = 'SUB'): string
    {
        $reference = trim((string) $request->reference);

        return $reference !== '' ? $reference : $prefix.Str::upper(Str::random(12));
    }

    /** The callback this checkout should use — the request's, else the configured one. */
    protected function callbackUrl(CheckoutRequest $request): string
    {
        return $request->callbackUrl ?? (string) $this->config('callback_url');
    }

    /**
     * Read a value from this gateway's config block (dot notation supported).
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }
}
