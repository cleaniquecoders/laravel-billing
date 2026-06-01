<?php

namespace CleaniqueCoders\LaravelBilling\Gateways;

use CleaniqueCoders\LaravelBilling\Contracts\Billable;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutIntent;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\WebhookEvent;
use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
use CleaniqueCoders\LaravelBilling\Models\Plan;
use CleaniqueCoders\LaravelBilling\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * toyyibPay driver — a one-time, hosted "bill" gateway (no native subscriptions).
 *
 * Config: secret_key (userSecretKey), category_code, callback_url, sandbox (bool).
 *
 * toyyibPay callbacks are NOT signed, so parseWebhook re-queries
 * getBillTransactions to confirm billpaymentStatus == 1 before trusting it.
 */
class ToyyibPayGateway extends Gateway
{
    public function createCheckout(Billable $billable, Plan $plan, PlanInterval $interval, string $returnUrl): CheckoutIntent
    {
        $orderId = 'SUB'.Str::upper(Str::random(12));

        $res = Http::asForm()
            ->post($this->base().'/index.php/api/createBill', [
                'userSecretKey' => (string) $this->config('secret_key'),
                'categoryCode' => (string) $this->config('category_code'),
                'billName' => Str::limit($plan->name, 30, ''),
                'billDescription' => $plan->name.' ('.$interval->value.')',
                'billPriceSetting' => 1,
                'billPayorInfo' => 1,
                'billAmount' => $plan->priceCents($interval),
                'billReturnUrl' => $returnUrl,
                'billCallbackUrl' => (string) $this->config('callback_url'),
                'billExternalReferenceNo' => $orderId,
                'billTo' => $billable->billingName(),
                'billEmail' => $billable->billingEmail(),
                'billPhone' => '0000000000',
                'billPaymentChannel' => '2',
            ])->throw()->json();

        $billCode = $res[0]['BillCode'] ?? null;

        if (! is_string($billCode) || $billCode === '') {
            throw new RuntimeException('toyyibPay did not return a BillCode.');
        }

        return new CheckoutIntent($this->base().'/'.$billCode, $billCode);
    }

    public function cancel(Subscription $subscription): void
    {
        // One-time gateway — nothing to cancel upstream.
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $billCode = (string) $request->input('billcode');

        if ($billCode === '') {
            return null;
        }

        $providerEventId = 'toyyibpay-'.((string) $request->input('refno', $billCode));

        // Unsigned callback — confirm against the API before trusting.
        if ($this->isPaid($billCode)) {
            return new WebhookEvent(
                type: WebhookEventType::SubscriptionActivated,
                externalId: $billCode,
                providerEventId: $providerEventId,
                rawPayload: $request->all(),
            );
        }

        // status_id 3 = failed; 2 = pending (ignored).
        if ((string) $request->input('status_id') === '3') {
            return new WebhookEvent(
                type: WebhookEventType::PaymentFailed,
                externalId: $billCode,
                providerEventId: $providerEventId,
                rawPayload: $request->all(),
            );
        }

        return null;
    }

    protected function isPaid(string $billCode): bool
    {
        $transactions = Http::asForm()
            ->post($this->base().'/index.php/api/getBillTransactions', [
                'billCode' => $billCode,
                'userSecretKey' => (string) $this->config('secret_key'),
            ])->json();

        return collect(is_array($transactions) ? $transactions : [])
            ->contains(fn ($txn) => (string) ($txn['billpaymentStatus'] ?? '') === '1');
    }

    protected function base(): string
    {
        return $this->config('sandbox')
            ? 'https://dev.toyyibpay.com'
            : 'https://toyyibpay.com';
    }
}
