<?php

namespace CleaniqueCoders\LaravelBilling\Events;

use CleaniqueCoders\LaravelBilling\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceIssued
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Invoice $invoice) {}
}
