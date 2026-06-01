<?php

namespace Workbench\App\Models;

use CleaniqueCoders\LaravelBilling\Concerns\HasSubscriptions;
use CleaniqueCoders\LaravelBilling\Contracts\Billable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Demo billable for the Testbench workbench. Mirrors what a host app would do:
 * implement Billable + use HasSubscriptions.
 */
class User extends Authenticatable implements Billable
{
    use HasFactory;
    use HasSubscriptions;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    /**
     * @return array<string,string>
     */
    public function billingAddress(): array
    {
        return [
            'No. 1, Jalan Contoh',
            '50000 Kuala Lumpur',
            'Malaysia',
        ];
    }
}
