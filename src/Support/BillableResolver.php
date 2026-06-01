<?php

namespace CleaniqueCoders\LaravelBilling\Support;

use CleaniqueCoders\LaravelBilling\Contracts\Billable;
use Closure;
use Illuminate\Http\Request;

/**
 * Resolves the billable the UI is scoped to for the current request. Defaults
 * to the authenticated user; apps may override via config('billing.billable_resolver')
 * with a closure (e.g. fn ($request) => $request->user()->currentTeam) to scope
 * billing to a Team/Workspace.
 */
class BillableResolver
{
    public function resolve(?Request $request = null): ?Billable
    {
        $request ??= request();

        $resolver = config('billing.billable_resolver');

        $billable = $resolver instanceof Closure
            ? $resolver($request)
            : $request->user();

        return $billable instanceof Billable ? $billable : null;
    }
}
