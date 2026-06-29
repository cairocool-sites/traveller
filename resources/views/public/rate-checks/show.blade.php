<x-layouts.public :meta-title="$metaTitle" :meta-description="$metaDescription">
    <section class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <nav class="text-sm text-slate-500" aria-label="Breadcrumb">
            <a href="{{ route('home', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.home') }}</a>
            <span>/</span>
            <a href="{{ route('hotels.index', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.hotels') }}</a>
            <span>/</span>
            <span>{{ __('public.booking.guest_details_title') }}</span>
        </nav>

        <div class="mt-6 rounded border border-slate-200 bg-white p-5">
            <h1 class="text-2xl font-semibold text-slate-950">{{ __('public.booking.guest_details_title') }}</h1>
            <p class="mt-2 text-slate-600">{{ $rateCheck->room_snapshot['room_name'] }} · {{ $money->formatMinor($rateCheck->checked_amount_minor ?? $rateCheck->original_amount_minor, $rateCheck->currency->code) }}</p>
            @if ($rateCheck->price_changed)
                <div class="mt-4 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">{{ __('public.booking.price_changed') }}</div>
            @endif
        </div>

        <livewire:guest-details-form :rate-check-uuid="$rateCheck->public_uuid" />
    </section>
</x-layouts.public>
