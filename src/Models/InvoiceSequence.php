<?php

namespace CleaniqueCoders\LaravelBilling\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $year
 * @property int $next_number
 */
class InvoiceSequence extends Model
{
    protected $guarded = [];

    protected $casts = [
        'year' => 'integer',
        'next_number' => 'integer',
    ];

    public function getTable()
    {
        return config('billing.tables.invoice_sequences', 'invoice_sequences');
    }
}
