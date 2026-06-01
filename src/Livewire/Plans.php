<?php

namespace CleaniqueCoders\LaravelBilling\Livewire;

use CleaniqueCoders\LaravelBilling\Enums\PlanInterval;
use CleaniqueCoders\LaravelBilling\Facades\Billing;
use CleaniqueCoders\LaravelBilling\Services\PlanRepository;
use CleaniqueCoders\LaravelBilling\Support\BillableResolver;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Lists available plans and starts checkout for the resolved billable.
 */
class Plans extends Component
{
    public string $interval = 'monthly';

    /**
     * Begin checkout for the chosen plan and redirect to the gateway. With the
     * local auto gateway the subscription activates synchronously and the
     * redirect lands on the success page.
     */
    public function subscribe(string $tier)
    {
        $billable = app(BillableResolver::class)->resolve();
        abort_if($billable === null, 403);

        $plan = app(PlanRepository::class)->find($tier);
        abort_if($plan === null, 404);

        $intent = Billing::checkout(
            $billable,
            $plan,
            PlanInterval::from($this->interval),
            route('billing.success'),
        );

        return redirect()->away($intent->redirectUrl);
    }

    public function render(): View
    {
        $billable = app(BillableResolver::class)->resolve();

        return view('billing::livewire.plans', [
            'plans' => app(PlanRepository::class)->all(),
            'currentTier' => $billable?->subscription()?->plan_tier,
            'currency' => config('billing.currency', 'MYR'),
        ])->layout(config('billing.layout', 'billing::layouts.app'));
    }
}
