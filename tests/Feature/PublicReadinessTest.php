<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('renders public trust and policy pages without supplier requests', function (): void {
    Http::preventStrayRequests();

    get(route('pages.about', ['locale' => 'en']))
        ->assertOk()
        ->assertSee('About Cairo Cool Travel')
        ->assertSee('Hotel-first focus')
        ->assertDontSee('Mock Supplier');

    get(route('pages.privacy', ['locale' => 'ar']))
        ->assertOk()
        ->assertSee('إشعار الخصوصية')
        ->assertSee('البيانات الحساسة');

    get(route('pages.support', ['locale' => 'en']))
        ->assertOk()
        ->assertSee('Support')
        ->assertSee('local booking reference');

    Http::assertNothingSent();
});

it('includes public readiness pages in the sitemap', function (): void {
    get(route('catalogue.sitemap'))
        ->assertOk()
        ->assertSee('/about', false)
        ->assertSee('/terms', false)
        ->assertSee('/privacy', false)
        ->assertSee('/payment-policy', false)
        ->assertSee('/cancellation-policy', false)
        ->assertSee('/support', false);
});
