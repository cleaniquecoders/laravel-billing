<?php

namespace CleaniqueCoders\LaravelBilling\Http\Controllers;

use CleaniqueCoders\LaravelBilling\Facades\Billing;
use CleaniqueCoders\LaravelBilling\Gateways\LocalGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Dev-only checkout for the bundled LocalGateway. Registered solely when
 * config('billing.gateways.local.enabled') is true and the app is not in
 * production.
 */
class LocalCheckoutController extends Controller
{
    public function show(Request $request)
    {
        $payload = LocalGateway::verify((string) $request->query('token'));

        abort_if($payload === null, 404);

        return view('billing::local.checkout', [
            'token' => $request->query('token'),
            'payload' => $payload,
        ]);
    }

    public function callback(Request $request): RedirectResponse
    {
        $payload = LocalGateway::verify((string) $request->input('token'));

        abort_if($payload === null, 404);

        $returnUrl = $payload['return_url'] ?? '/';

        if ($request->input('decision') === 'approve') {
            $event = Billing::gateway('local')->parseWebhook($request);

            if ($event !== null) {
                Billing::handle($event);
            }
        }

        return redirect()->to($returnUrl);
    }
}
