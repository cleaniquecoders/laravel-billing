<?php

use CleaniqueCoders\LaravelBilling\Gateways\Support\RedirectForm;

it('round-trips a signed action and fields', function () {
    $token = RedirectForm::sign('https://gateway.test/pay', [
        'RefNo' => 'REF123',
        'Amount' => '49.00',
        'Currency' => 'MYR',
    ]);

    $payload = RedirectForm::verify($token);

    expect($payload)->not->toBeNull()
        ->and($payload['action'])->toBe('https://gateway.test/pay')
        ->and($payload['fields']['RefNo'])->toBe('REF123')
        ->and($payload['fields']['Amount'])->toBe('49.00');
});

it('rejects a malformed or tampered token', function () {
    $token = RedirectForm::sign('https://gateway.test/pay', ['RefNo' => 'REF123']);

    // Mutate the first byte of the data segment so the HMAC no longer matches.
    $mutated = ($token[0] === 'A' ? 'B' : 'A').substr($token, 1);

    expect(RedirectForm::verify('no-dot-here'))->toBeNull()
        ->and(RedirectForm::verify($token.'tampered'))->toBeNull()
        ->and(RedirectForm::verify($mutated))->toBeNull();
});

it('renders an auto-submitting form for a valid token', function () {
    $token = RedirectForm::sign('https://gateway.test/pay', [
        'RefNo' => 'REF123',
        'Amount' => '49.00',
    ]);

    $this->get(route('billing.gateway.redirect', ['token' => $token]))
        ->assertOk()
        ->assertSee('action="https://gateway.test/pay"', false)
        ->assertSee('name="RefNo"', false)
        ->assertSee('document.forms[0].submit()', false);
});

it('404s on an invalid redirect token', function () {
    $this->get(route('billing.gateway.redirect', ['token' => 'bogus']))
        ->assertNotFound();
});
