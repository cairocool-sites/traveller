<?php

test('travel locale configuration matches the Laravel locale configuration', function () {
    expect(config('travel.locales.default'))->toBe(config('app.locale'))
        ->and(config('travel.locales.fallback'))->toBe(config('app.fallback_locale'));
});
