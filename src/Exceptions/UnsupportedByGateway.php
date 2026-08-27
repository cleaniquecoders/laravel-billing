<?php

namespace CleaniqueCoders\LaravelBilling\Exceptions;

use RuntimeException;

/**
 * This gateway genuinely cannot do that.
 *
 * Thrown rather than answered with null, because null already means something
 * else on every method that has one — "asked, and there is nothing there".
 * A caller must be able to tell "no such payment" from "this gateway has no
 * way to be asked", since only the first is an answer.
 */
final class UnsupportedByGateway extends RuntimeException
{
    public static function cannot(string $gateway, string $capability): self
    {
        return new self("The {$gateway} gateway cannot {$capability}.");
    }
}
