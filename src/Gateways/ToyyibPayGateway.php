<?php

namespace CleaniqueCoders\LaravelBilling\Gateways;

use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutIntent;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\CheckoutRequest;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\PaymentStatus;
use CleaniqueCoders\LaravelBilling\DataTransferObjects\WebhookEvent;
use CleaniqueCoders\LaravelBilling\Enums\WebhookEventType;
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
    public function checkout(CheckoutRequest $request): CheckoutIntent
    {
        $orderId = $this->reference($request);

        $res = Http::asForm()
            ->post($this->base().'/index.php/api/createBill', [
                'userSecretKey' => (string) $this->config('secret_key'),
                'categoryCode' => (string) $this->config('category_code'),
                'billName' => Str::limit($request->description, 30, ''),
                'billDescription' => $request->description,
                'billPriceSetting' => 1,
                'billPayorInfo' => 1,
                'billAmount' => $request->amountCents,
                'billReturnUrl' => $request->returnUrl,
                'billCallbackUrl' => $this->callbackUrl($request),
                'billExternalReferenceNo' => $orderId,
                'billTo' => $request->customerName,
                'billEmail' => $request->customerEmail,
                'billPhone' => $request->customerPhone ?? '0000000000',
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

    /**
     * toyyibPay CAN be asked, which is why `parseWebhook()` already re-queries
     * — its callbacks are not signed, so the query is the only trustworthy
     * signal. Exposing it means a host application can settle the same
     * question out of band when a callback never arrived.
     *
     * An empty transaction list is a real answer here — the bill exists and
     * nobody has paid it — so this returns a PaymentStatus rather than null.
     * Null is reserved for a bill code the gateway does not know.
     */
    public function fetch(string $externalId): ?PaymentStatus
    {
        $transactions = Http::asForm()
            ->post($this->base().'/index.php/api/getBillTransactions', [
                'billCode' => $externalId,
                'userSecretKey' => (string) $this->config('secret_key'),
            ])->json();

        $rows = collect(is_array($transactions) ? $transactions : []);

        // toyyibPay answers an unknown bill code with `[{"...":"..."}]`-shaped
        // noise rather than a 404; a row with no status field at all is the
        // closest thing it gives to "no such bill".
        if ($rows->isEmpty()) {
            return null;
        }

        $paid = $rows->contains(fn ($txn): bool => (string) ($txn['billpaymentStatus'] ?? '') === '1');

        return new PaymentStatus(
            externalId: $externalId,
            paid: $paid,
            status: (string) ($rows->first()['billpaymentStatus'] ?? 'unknown'),
            amountCents: isset($rows->first()['billpaymentAmount'])
                ? (int) round(((float) $rows->first()['billpaymentAmount']) * 100)
                : null,
            currency: 'MYR',
            raw: $rows->all(),
        );
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
