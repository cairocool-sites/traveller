<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Facility;
use App\Models\User;
use App\Services\Currency\CurrencyConversionService;
use App\Services\Currency\LatestExchangeRateResolver;
use App\Services\Currency\MissingExchangeRateException;
use Database\Seeders\CoreReferenceDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed();
});

it('seeds countries cities currencies and facilities idempotently', function () {
    $counts = [
        'countries' => Country::query()->count(),
        'cities' => City::query()->count(),
        'currencies' => Currency::query()->count(),
        'facilities' => Facility::query()->count(),
    ];

    $this->seed(CoreReferenceDataSeeder::class);

    expect(Country::query()->count())->toBe($counts['countries'])
        ->and(City::query()->count())->toBe($counts['cities'])
        ->and(Currency::query()->count())->toBe($counts['currencies'])
        ->and(Facility::query()->count())->toBe($counts['facilities']);
});

it('keeps USD as the only initial active base currency', function () {
    expect(Currency::query()->where('is_active', true)->where('is_base', true)->pluck('code')->all())
        ->toBe(['USD']);
});

it('normalizes currency and country codes to uppercase', function () {
    $currency = Currency::query()->create([
        'code' => 'jpy',
        'name_en' => 'Japanese Yen',
        'name_ar' => 'ين ياباني',
        'symbol' => '¥',
        'decimal_places' => 0,
    ]);

    $country = Country::query()->create([
        'iso2' => 'fr',
        'iso3' => 'fra',
        'name_en' => 'France',
        'name_ar' => 'فرنسا',
    ]);

    expect($currency->code)->toBe('JPY')
        ->and($country->iso2)->toBe('FR')
        ->and($country->iso3)->toBe('FRA');
});

it('rejects duplicate country iso codes', function () {
    expect(fn () => Country::query()->create([
        'iso2' => 'EG',
        'iso3' => 'EGX',
        'name_en' => 'Duplicate Egypt',
        'name_ar' => 'مصر مكررة',
    ]))->toThrow(QueryException::class);
});

it('keeps city relationships scoped to their country', function () {
    $egypt = Country::query()->where('iso2', 'EG')->firstOrFail();
    $cairo = City::query()->where('name_en', 'Cairo')->firstOrFail();

    expect($cairo->country->is($egypt))->toBeTrue();
});

it('rejects invalid exchange rates', function () {
    $egp = Currency::query()->where('code', 'EGP')->firstOrFail();

    expect(fn () => ExchangeRate::query()->create([
        'base_currency_id' => $egp->id,
        'quote_currency_id' => $egp->id,
        'rate' => '1.0000000000',
        'source' => 'manual',
        'effective_at' => now(),
    ]))->toThrow(ValidationException::class);

    $usd = Currency::query()->where('code', 'USD')->firstOrFail();

    expect(fn () => ExchangeRate::query()->create([
        'base_currency_id' => $egp->id,
        'quote_currency_id' => $usd->id,
        'rate' => '0.0000000000',
        'source' => 'manual',
        'effective_at' => now(),
    ]))->toThrow(ValidationException::class);
});

it('resolves the latest active applicable exchange rate', function () {
    $usd = Currency::query()->where('code', 'USD')->firstOrFail();
    $egp = Currency::query()->where('code', 'EGP')->firstOrFail();

    ExchangeRate::query()->create([
        'base_currency_id' => $usd->id,
        'quote_currency_id' => $egp->id,
        'rate' => '48.5000000000',
        'source' => 'manual',
        'effective_at' => now()->subDay(),
    ]);
    ExchangeRate::query()->create([
        'base_currency_id' => $usd->id,
        'quote_currency_id' => $egp->id,
        'rate' => '49.0000000000',
        'source' => 'manual',
        'effective_at' => now(),
    ]);

    $rate = app(LatestExchangeRateResolver::class)->resolve('USD', 'EGP');

    expect($rate->rate)->toBe('49.0000000000');
});

it('converts currency with decimal precision and explicit missing-rate failures', function () {
    $usd = Currency::query()->where('code', 'USD')->firstOrFail();
    $egp = Currency::query()->where('code', 'EGP')->firstOrFail();

    ExchangeRate::query()->create([
        'base_currency_id' => $usd->id,
        'quote_currency_id' => $egp->id,
        'rate' => '48.5500000000',
        'source' => 'manual',
        'effective_at' => now(),
    ]);

    expect(app(CurrencyConversionService::class)->convert('100.00', 'USD', 'EGP'))->toBe('4855.00')
        ->and(fn () => app(CurrencyConversionService::class)->convert('100.00', 'USD', 'EUR'))
        ->toThrow(MissingExchangeRateException::class);
});

it('protects reference data admin resources by role permissions', function () {
    $unauthorized = User::factory()->create();
    $unauthorized->assignRole('reservation_agent');

    $this->actingAs($unauthorized)
        ->get('/admin/countries')
        ->assertForbidden();

    $contentManager = User::factory()->create();
    $contentManager->assignRole('content_manager');

    $this->actingAs($contentManager)
        ->get('/admin/countries')
        ->assertOk();

    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');

    $this->actingAs($auditor);

    expect(Gate::allows('viewAny', Country::class))->toBeTrue()
        ->and(Gate::denies('create', Country::class))->toBeTrue();
});

it('keeps Arabic default locale and English fallback locale', function () {
    expect(config('app.locale'))->toBe('ar')
        ->and(config('app.fallback_locale'))->toBe('en');
});
