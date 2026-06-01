<div class="space-y-6">
    <flux:heading size="xl">Billing</flux:heading>

    <div class="flex gap-6 border-b border-zinc-200 dark:border-zinc-700">
        <button type="button" wire:click="$set('tab', 'overview')"
            class="-mb-px border-b-2 px-1 pb-3 text-sm font-medium {{ $tab === 'overview' ? 'border-zinc-900 dark:border-white' : 'border-transparent text-zinc-500' }}">
            Overview
        </button>
        <button type="button" wire:click="$set('tab', 'invoices')"
            class="-mb-px border-b-2 px-1 pb-3 text-sm font-medium {{ $tab === 'invoices' ? 'border-zinc-900 dark:border-white' : 'border-transparent text-zinc-500' }}">
            Invoices
        </button>
    </div>

    @if($tab === 'overview')
        @if($subscription)
            <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="lg">{{ $plan->name }} plan</flux:heading>
                        <flux:text class="mt-1">{{ ucfirst($subscription->interval->value) }} billing</flux:text>
                    </div>
                    <flux:badge size="sm" :color="$subscription->grantsAccess() ? 'green' : 'zinc'">
                        {{ ucfirst(str_replace('_', ' ', $subscription->status->value)) }}
                    </flux:badge>
                </div>

                <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-zinc-500">Current period</dt>
                        <dd>{{ $subscription->current_period_start?->toFormattedDateString() }} — {{ $subscription->current_period_end?->toFormattedDateString() }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">{{ $subscription->cancel_at_period_end ? 'Access until' : 'Renews on' }}</dt>
                        <dd>{{ $subscription->current_period_end?->toFormattedDateString() }}</dd>
                    </div>
                </dl>

                @if($subscription->cancel_at_period_end)
                    <div class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                        Your subscription is set to cancel at the end of the current period.
                    </div>
                @elseif($subscription->onTrial())
                    <div class="mt-4 rounded-lg bg-blue-50 p-3 text-sm text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                        Trial ends {{ $subscription->trial_ends_at?->toFormattedDateString() }}.
                    </div>
                @endif

                <div class="mt-6 flex gap-3">
                    <flux:button :href="route('billing.plans')" variant="primary">Change plan</flux:button>
                    @if($subscription->cancel_at_period_end)
                        <flux:button wire:click="resume" variant="ghost">Resume subscription</flux:button>
                    @else
                        <flux:button wire:click="confirmCancel" variant="ghost">Cancel subscription</flux:button>
                    @endif
                </div>
            </div>
        @else
            <div class="rounded-xl border border-zinc-200 p-8 text-center dark:border-zinc-700">
                <flux:text>You don't have an active subscription.</flux:text>
                <div class="mt-4">
                    <flux:button :href="route('billing.plans')" variant="primary">Browse plans</flux:button>
                </div>
            </div>
        @endif
    @else
        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 text-zinc-500 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 font-medium">Due date</th>
                        <th class="px-4 py-3 font-medium">Description</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 text-right font-medium">Invoice total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse($invoices as $invoice)
                        <tr wire:click="selectInvoice('{{ $invoice->uuid }}')"
                            class="cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-4 py-3">{{ ($invoice->period_end ?? $invoice->issued_at)?->toFormattedDateString() }}</td>
                            <td class="px-4 py-3">{{ ucfirst($invoice->interval->value) }} invoice</td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" :color="$invoice->isPaid() ? 'green' : 'zinc'">
                                    {{ ucfirst($invoice->status->value) }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-right">{{ number_format($invoice->totalMajor(), 2) }} {{ $invoice->currency }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-zinc-500">No invoices yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    {{-- Invoice detail panel --}}
    @if($selectedInvoice)
        <div class="fixed inset-0 z-50 flex justify-end bg-black/40" wire:click.self="clearInvoice">
            <div class="h-full w-full max-w-md overflow-y-auto bg-white p-6 shadow-xl dark:bg-zinc-900">
                <div class="flex items-start justify-between">
                    <div>
                        <flux:heading size="lg">{{ ucfirst($selectedInvoice->interval->value) }} invoice</flux:heading>
                        <flux:text>Issued {{ $selectedInvoice->issued_at?->toFormattedDateString() }}</flux:text>
                    </div>
                    <button type="button" wire:click="clearInvoice" class="text-zinc-400 hover:text-zinc-600">
                        <flux:icon.x-mark class="size-5" />
                    </button>
                </div>

                <dl class="mt-6 grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-zinc-500">Invoice number</dt>
                        <dd>{{ $selectedInvoice->number }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Status</dt>
                        <dd>{{ ucfirst($selectedInvoice->status->value) }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Payment method</dt>
                        <dd>{{ $selectedInvoice->paymentMethod() ?? '—' }}</dd>
                    </div>
                </dl>

                <div class="mt-6">
                    <div class="text-sm text-zinc-500">Cost breakdown</div>
                    <div class="mt-2 space-y-2 text-sm">
                        @foreach($selectedInvoice->lineItems() as $item)
                            <div class="flex justify-between">
                                <span>{{ $item['description'] }}@if(($item['qty'] ?? 1) > 1) &times; {{ $item['qty'] }}@endif</span>
                                <span>{{ number_format(($item['amount_cents'] ?? 0) / 100, 2) }} {{ $selectedInvoice->currency }}</span>
                            </div>
                        @endforeach
                        <div class="flex justify-between border-t border-zinc-200 pt-2 dark:border-zinc-700">
                            <span>Subtotal</span>
                            <span>{{ number_format($selectedInvoice->subtotalMajor(), 2) }} {{ $selectedInvoice->currency }}</span>
                        </div>
                        @if($selectedInvoice->tax_cents > 0)
                            <div class="flex justify-between">
                                <span>{{ $selectedInvoice->tax_label ?? 'Tax' }}</span>
                                <span>{{ number_format($selectedInvoice->taxMajor(), 2) }} {{ $selectedInvoice->currency }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between font-semibold">
                            <span>Total</span>
                            <span>{{ number_format($selectedInvoice->totalMajor(), 2) }} {{ $selectedInvoice->currency }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex gap-3">
                    <flux:button :href="route('billing.invoices.download', $selectedInvoice->uuid)" variant="primary" icon="arrow-down-tray">
                        Download PDF
                    </flux:button>
                    @if($selectedInvoice->isPaid())
                        <flux:button :href="route('billing.invoices.receipt', $selectedInvoice->uuid)" variant="ghost">
                            Download receipt
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Cancel confirmation --}}
    @if($showCancelModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="w-full max-w-sm rounded-xl bg-white p-6 dark:bg-zinc-900">
                <flux:heading size="lg">Cancel subscription?</flux:heading>
                <flux:text class="mt-2">You'll keep access until the end of your current billing period.</flux:text>
                <div class="mt-6 flex justify-end gap-3">
                    <flux:button wire:click="$set('showCancelModal', false)" variant="ghost">Keep subscription</flux:button>
                    <flux:button wire:click="cancel" variant="danger">Cancel subscription</flux:button>
                </div>
            </div>
        </div>
    @endif
</div>
