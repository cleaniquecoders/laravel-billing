<?php

namespace CleaniqueCoders\LaravelBilling\Gateways;

use CleaniqueCoders\LaravelBilling\Contracts\PaymentGateway;
use CleaniqueCoders\LaravelBilling\Gateways\Concerns\SignsPayloads;

/**
 * Base class for the bundled real gateway drivers. Holds the gateway's config
 * block (injected by BillingManager::resolve()) and the SignsPayloads helpers.
 * Concrete drivers implement the three PaymentGateway methods.
 */
abstract class Gateway implements PaymentGateway
{
    use SignsPayloads;

    /**
     * @param  array<string,mixed>  $config  the config('billing.gateways.<name>') block
     */
    public function __construct(protected array $config) {}

    /**
     * Read a value from this gateway's config block (dot notation supported).
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }
}
