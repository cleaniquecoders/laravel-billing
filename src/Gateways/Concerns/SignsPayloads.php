<?php

namespace CleaniqueCoders\LaravelBilling\Gateways\Concerns;

/**
 * Signature primitives shared by gateway drivers. Real gateways sign requests
 * and verify callbacks with one of a handful of schemes — these helpers cover
 * the common ones so a driver stays a thin mapping over the contract.
 *
 * Use in a driver:  `use SignsPayloads;` then `$this->sha1Base64(...)` etc.
 */
trait SignsPayloads
{
    /** Plain MD5 hex digest. */
    protected function md5(string $data): string
    {
        return md5($data);
    }

    /** Base64-encoded raw SHA-1 — iPay88's signature scheme. */
    protected function sha1Base64(string $data): string
    {
        return base64_encode(sha1($data, true));
    }

    /** SHA-256 hex digest — eGHL and others. */
    protected function sha256(string $data): string
    {
        return hash('sha256', $data);
    }

    /** SHA-512 hex digest — eGHL (merchant-configurable). */
    protected function sha512(string $data): string
    {
        return hash('sha512', $data);
    }

    /**
     * Keyed HMAC — Billplz (X-Signature), SecurePay, BayarCash, senangPay.
     *
     * @param  string  $algo  e.g. 'sha256', 'sha512'
     */
    protected function hmac(string $algo, string $data, string $secret): string
    {
        return hash_hmac($algo, $data, $secret);
    }

    /**
     * Constant-time signature comparison. Always use this to compare an
     * incoming callback signature against the recomputed one.
     */
    protected function signaturesMatch(string $expected, string $actual): bool
    {
        return hash_equals($expected, $actual);
    }
}
