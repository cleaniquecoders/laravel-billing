<?php

namespace CleaniqueCoders\LaravelBilling\Enums;

enum InvoiceStatus: string
{
    case Paid = 'paid';
    case Refunded = 'refunded';
    case Void = 'void';
}
