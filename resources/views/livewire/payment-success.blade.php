<div class="mx-auto max-w-md">
    @if($invoice)
        <div class="rounded-2xl border border-zinc-200 bg-white p-8 text-center shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                <flux:icon.check class="size-6 text-green-600 dark:text-green-400" />
            </div>

            <flux:text class="mt-4">Invoice paid</flux:text>
            <div class="mt-1 text-4xl font-bold">{{ number_format($invoice->totalMajor(), 2) }} {{ $invoice->currency }}</div>

            <dl class="mt-8 space-y-3 text-left text-sm">
                <div class="flex justify-between">
                    <dt class="text-zinc-500">Invoice number</dt>
                    <dd>{{ $invoice->number }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">Payment date</dt>
                    <dd>{{ $invoice->paid_at?->toFormattedDateString() }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">Payment method</dt>
                    <dd>{{ $invoice->paymentMethod() ?? '—' }}</dd>
                </div>
            </dl>

            <div class="mt-8 grid grid-cols-2 gap-3">
                <flux:button :href="route('billing.invoices.download', $invoice->uuid)" variant="ghost">Download invoice</flux:button>
                <flux:button :href="route('billing.invoices.receipt', $invoice->uuid)" variant="primary">Download receipt</flux:button>
            </div>
        </div>

        <div class="mt-6 text-center">
            <flux:button :href="route('billing.portal')" variant="ghost">Back to billing</flux:button>
        </div>
    @else
        <div class="rounded-2xl border border-zinc-200 p-8 text-center dark:border-zinc-700">
            <flux:text>No recent payment found.</flux:text>
            <div class="mt-4">
                <flux:button :href="route('billing.portal')" variant="primary">Go to billing</flux:button>
            </div>
        </div>
    @endif
</div>
