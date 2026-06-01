<div class="space-y-8">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Plans</flux:heading>

        <div class="inline-flex rounded-lg bg-zinc-100 p-1 dark:bg-zinc-800">
            <button type="button" wire:click="$set('interval', 'monthly')"
                class="rounded-md px-3 py-1 text-sm font-medium {{ $interval === 'monthly' ? 'bg-white shadow dark:bg-zinc-700' : 'text-zinc-500' }}">
                Monthly
            </button>
            <button type="button" wire:click="$set('interval', 'annual')"
                class="rounded-md px-3 py-1 text-sm font-medium {{ $interval === 'annual' ? 'bg-white shadow dark:bg-zinc-700' : 'text-zinc-500' }}">
                Annual
            </button>
        </div>
    </div>

    @php($intervalEnum = \CleaniqueCoders\LaravelBilling\Enums\PlanInterval::from($interval))

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($plans as $plan)
            <div class="flex flex-col rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ $plan->name }}</flux:heading>
                    @if($currentTier === $plan->tier)
                        <flux:badge color="green" size="sm">Current</flux:badge>
                    @endif
                </div>

                @if($plan->tagline)
                    <flux:text class="mt-1">{{ $plan->tagline }}</flux:text>
                @endif

                <div class="mt-4">
                    <span class="text-3xl font-bold">{{ number_format($plan->priceCents($intervalEnum) / 100, 2) }}</span>
                    <span class="text-zinc-500">{{ $currency }} / {{ $interval === 'monthly' ? 'mo' : 'yr' }}</span>
                </div>

                @if(! empty($plan->features))
                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach($plan->features as $feature)
                            <li class="flex items-center gap-2">
                                <flux:icon.check class="size-4 text-green-500" />{{ $feature }}
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="mt-auto pt-6">
                    @if($currentTier === $plan->tier)
                        <flux:button variant="ghost" class="w-full" disabled>Current plan</flux:button>
                    @else
                        <flux:button variant="primary" class="w-full"
                            wire:click="subscribe('{{ $plan->tier }}')" wire:loading.attr="disabled">
                            {{ $currentTier ? 'Switch to '.$plan->name : 'Subscribe' }}
                        </flux:button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
