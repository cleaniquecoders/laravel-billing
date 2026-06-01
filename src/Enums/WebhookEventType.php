<?php

namespace CleaniqueCoders\LaravelBilling\Enums;

enum WebhookEventType: string
{
    case SubscriptionActivated = 'subscription.activated';
    case SubscriptionRenewed = 'subscription.renewed';
    case PaymentSucceeded = 'payment.succeeded';
    case PaymentFailed = 'payment.failed';
    case SubscriptionCanceled = 'subscription.canceled';
}
