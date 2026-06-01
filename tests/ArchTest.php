<?php

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->each->not->toBeUsed();

arch('contracts are interfaces')
    ->expect('CleaniqueCoders\LaravelBilling\Contracts')
    ->toBeInterfaces();

arch('enums are enums')
    ->expect('CleaniqueCoders\LaravelBilling\Enums')
    ->toBeEnums();

arch('events do not depend on the HTTP layer')
    ->expect('CleaniqueCoders\LaravelBilling\Events')
    ->not->toUse('Illuminate\Http');

arch('models do not use the raw DB facade')
    ->expect('CleaniqueCoders\LaravelBilling\Models')
    ->not->toUse('Illuminate\Support\Facades\DB');
