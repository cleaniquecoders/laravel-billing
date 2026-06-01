<?php

use CleaniqueCoders\LaravelBilling\Gateways\Concerns\SignsPayloads;

/**
 * Exposes the protected SignsPayloads helpers for testing, mirroring how a real
 * driver consumes the trait.
 */
function signer(): object
{
    return new class
    {
        use SignsPayloads;

        public function call(string $method, ...$args): mixed
        {
            return $this->{$method}(...$args);
        }
    };
}

it('produces the expected digests for each scheme', function () {
    $s = signer();

    expect($s->call('md5', 'abc'))->toBe(md5('abc'))
        ->and($s->call('sha1Base64', 'abc'))->toBe(base64_encode(sha1('abc', true)))
        ->and($s->call('sha256', 'abc'))->toBe(hash('sha256', 'abc'))
        ->and($s->call('sha512', 'abc'))->toBe(hash('sha512', 'abc'))
        ->and($s->call('hmac', 'sha256', 'abc', 'secret'))->toBe(hash_hmac('sha256', 'abc', 'secret'));
});

it('sha1Base64 matches the iPay88 signature scheme', function () {
    // MerchantKey.MerchantCode.RefNo.Amount.Currency with Amount stripped of separators.
    $payload = 'KEY'.'MERCHANT'.'REF123'.'1000'.'MYR';

    expect(signer()->call('sha1Base64', $payload))
        ->toBe(base64_encode(sha1($payload, true)));
});

it('compares signatures in constant time', function () {
    $s = signer();

    expect($s->call('signaturesMatch', 'abc', 'abc'))->toBeTrue()
        ->and($s->call('signaturesMatch', 'abc', 'abd'))->toBeFalse()
        ->and($s->call('signaturesMatch', 'abc', 'ab'))->toBeFalse();
});
