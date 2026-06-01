<?php

namespace CleaniqueCoders\LaravelBilling\Gateways\Support;

/**
 * Form-POST gateways (iPay88, eGHL, senangPay) need the customer's browser to
 * POST a set of signed fields to the gateway's entry URL. A CheckoutIntent only
 * carries a GET redirect URL, so a driver packs {action, fields} into an opaque,
 * app-key-signed token and returns route('billing.gateway.redirect', compact('token')).
 * That route renders billing::redirect-form, which auto-submits the POST.
 *
 * The token is tamper-evident (HMAC with the app key) — the same approach as
 * LocalGateway::sign() — so the redirect endpoint needs no other auth.
 */
class RedirectForm
{
    /**
     * Sign the destination URL and form fields into an opaque token.
     *
     * @param  array<string,scalar>  $fields
     */
    public static function sign(string $action, array $fields): string
    {
        $data = base64_encode((string) json_encode([
            'action' => $action,
            'fields' => $fields,
        ]));

        return $data.'.'.hash_hmac('sha256', $data, static::key());
    }

    /**
     * Verify and decode a token. Returns ['action' => string, 'fields' => array]
     * or null if the token is malformed or tampered with.
     *
     * @return array{action:string,fields:array<string,scalar>}|null
     */
    public static function verify(string $token): ?array
    {
        if (! str_contains($token, '.')) {
            return null;
        }

        [$data, $signature] = explode('.', $token, 2);

        if (! hash_equals(hash_hmac('sha256', $data, static::key()), $signature)) {
            return null;
        }

        $decoded = json_decode((string) base64_decode($data, true), true);

        if (! is_array($decoded) || ! isset($decoded['action']) || ! isset($decoded['fields']) || ! is_array($decoded['fields'])) {
            return null;
        }

        return ['action' => (string) $decoded['action'], 'fields' => $decoded['fields']];
    }

    protected static function key(): string
    {
        return (string) config('app.key');
    }
}
