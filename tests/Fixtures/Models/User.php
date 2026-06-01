<?php

namespace CleaniqueCoders\LaravelBilling\Tests\Fixtures\Models;

use CleaniqueCoders\LaravelBilling\Concerns\HasSubscriptions;
use CleaniqueCoders\LaravelBilling\Contracts\Billable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements Billable
{
    use HasSubscriptions;

    protected $table = 'users';

    protected $guarded = [];

    /**
     * @return array<string,string>
     */
    public function billingAddress(): array
    {
        return [
            'No. 1, Jalan Contoh',
            '50000 Kuala Lumpur',
        ];
    }
}
