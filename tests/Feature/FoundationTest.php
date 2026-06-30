<?php

use Illuminate\Foundation\Application;

it('boots the application', function () {
    expect(app())->toBeInstanceOf(Application::class);
});

it('returns a successful homepage response', function () {
    $this->get('/')->assertOk();
});

it('uses Arabic as the default locale', function () {
    expect(config('app.locale'))->toBe('ar');
});

it('uses English as the fallback locale', function () {
    expect(config('app.fallback_locale'))->toBe('en');
});

it('uses Africa Cairo as the application timezone', function () {
    expect(config('app.timezone'))->toBe('Africa/Cairo');
});

it('uses USD as the default travel currency', function () {
    expect(config('travel.currency.default'))->toBe('USD');
});

it('configures all supported travel currencies', function () {
    expect(config('travel.currency.supported'))->toBe([
        'EGP',
        'USD',
        'EUR',
        'SAR',
        'AED',
        'GBP',
    ]);
});
