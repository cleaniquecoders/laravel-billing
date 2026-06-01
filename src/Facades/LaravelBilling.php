<?php

namespace CleaniqueCoders\LaravelBilling\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \CleaniqueCoders\LaravelBilling\LaravelBilling
 */
class LaravelBilling extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \CleaniqueCoders\LaravelBilling\LaravelBilling::class;
    }
}
