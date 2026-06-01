<?php

use CleaniqueCoders\LaravelBilling\Enums\InvoiceStatus;
use CleaniqueCoders\LaravelBilling\Events\InvoiceIssued;
use CleaniqueCoders\LaravelBilling\Mail\InvoiceIssuedMail;
use CleaniqueCoders\LaravelBilling\Models\Subscription;
use CleaniqueCoders\LaravelBilling\Services\IssueInvoice;
use CleaniqueCoders\LaravelBilling\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('billing.store', 'config');
    config()->set('billing.plans', [
        'pro' => ['name' => 'Pro', 'price_cents' => ['monthly' => 4900, 'annual' => 49000], 'limits' => []],
    ]);
    config()->set('billing.invoice.disk', 'local');
    Storage::fake('local');
    Mail::fake();
});

it('generates sequential, zero-padded invoice numbers per year', function () {
    config()->set('billing.invoice.prefix', 'INV');

    expect(IssueInvoice::nextNumber(2026))->toBe('INV-2026-000001')
        ->and(IssueInvoice::nextNumber(2026))->toBe('INV-2026-000002')
        ->and(IssueInvoice::nextNumber(2027))->toBe('INV-2027-000001');
});

it('issues an invoice, stores the PDF and fires the event', function () {
    Event::fake([InvoiceIssued::class]);

    $user = User::create(['name' => 'Ali', 'email' => 'ali@example.test']);
    $subscription = Subscription::factory()->create([
        'billable_type' => $user->getMorphClass(),
        'billable_id' => $user->getKey(),
        'plan_tier' => 'pro',
    ]);

    $invoice = app(IssueInvoice::class)($subscription, email: true);

    expect($invoice->total_cents)->toBe(4900)
        ->and($invoice->currency)->toBe('MYR')
        ->and($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->storage_path)->not->toBeNull();

    Storage::disk('local')->assertExists($invoice->storage_path);
    Event::assertDispatched(InvoiceIssued::class);
    Mail::assertQueued(InvoiceIssuedMail::class);
});
