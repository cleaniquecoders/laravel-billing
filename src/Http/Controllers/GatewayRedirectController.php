<?php

namespace CleaniqueCoders\LaravelBilling\Http\Controllers;

use CleaniqueCoders\LaravelBilling\Gateways\Support\RedirectForm;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Renders the auto-submitting bridge form for form-POST gateways. The token is
 * an app-key-signed {action, fields} payload produced by RedirectForm::sign() in
 * a driver's createCheckout(); a tampered or missing token 404s.
 */
class GatewayRedirectController extends Controller
{
    public function __invoke(Request $request): View
    {
        $payload = RedirectForm::verify((string) $request->query('token'));

        abort_if($payload === null, 404);

        return view('billing::redirect-form', [
            'action' => $payload['action'],
            'fields' => $payload['fields'],
        ]);
    }
}
