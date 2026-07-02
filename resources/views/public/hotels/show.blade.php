@php
    $translation = $canonicalHotel?->translation(app()->getLocale());
    $hbxLanguage = app()->getLocale() === 'ar' ? 'ARA' : 'ENG';
    $hbxTranslation = $hbxContentHotel?->translations?->firstWhere('language', $hbxLanguage)
        ?? $hbxContentHotel?->translations?->firstWhere('language', 'ENG')
        ?? $hbxContentHotel?->translations?->first();
    $hbxHotelName = app()->getLocale() === 'ar'
        ? ($hbxContentHotel?->name_ar ?: $hbxContentHotel?->hotel_name)
        : ($hbxContentHotel?->name_en ?: $hbxContentHotel?->hotel_name);
    $hotelName = $translation?->translated_name ?? $canonicalHotel?->name ?? $hbxTranslation?->name ?? $hbxHotelName ?? $result['name'];
    $hbxPrimaryImage = $hbxContentHotel?->images?->where('is_active', true)->sortBy([
        ['is_primary', 'desc'],
        ['sort_order', 'asc'],
    ])->first();
    $heroImageUrl = $hbxPrimaryImage?->url('bigger') ?: ($result['primary_image'] ?? null);
    $description = $translation?->description
        ?? $translation?->short_description
        ?? $hbxTranslation?->description
        ?? $hbxContentHotel?->seo_description
        ?? $supplierDetails?->hotel->name
        ?? $result['name'];
    $locationLabel = $canonicalHotel?->city?->name_en ?? $hbxTranslation?->address ?? $hbxContentHotel?->address ?? $result['location'];
    $mapAddress = $canonicalHotel?->address_line_1 ?? $hbxTranslation?->address ?? $hbxContentHotel?->address ?? $result['location'];
    $facilities = $canonicalHotel?->facilities?->isNotEmpty()
        ? $canonicalHotel->facilities
        : ($hbxContentHotel?->facilities?->where('is_active', true)->take(12)->values() ?? collect($result['facilities'] ?? []));
    $rates = $result['rates'] ?? ($supplierDetails?->hotel->rooms ? collect($supplierDetails->hotel->rooms)->map(fn ($rate) => [
        'room_name' => $rate->roomName,
        'board_basis' => $rate->boardBasis->value,
        'total' => $rate->totalAmount->jsonSerialize(),
        'refundability' => $rate->refundability->value,
        'cancellation_summary' => app(\App\Services\PublicSearch\CancellationSummaryService::class)->summarize($rate->cancellationPolicies, app()->getLocale()),
        'occupancy' => $rate->occupancy->jsonSerialize(),
        'requires_check_rate' => (bool) ($rate->metadata['requires_check_rate'] ?? false),
    ])->all() : []);
@endphp
<x-layouts.public :meta-title="$metaTitle" :meta-description="$metaDescription">
    <section class="border-b border-slate-200 bg-white">
        <div class="cct-shell py-8">
            <nav class="text-sm font-semibold text-slate-500" aria-label="Breadcrumb">
                <a class="hover:text-[#0F766E]" href="{{ route('home', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.home') }}</a>
                <span class="px-2 text-slate-300">/</span>
                <a class="hover:text-[#0F766E]" href="{{ route('hotels.index', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.hotels') }}</a>
                <span class="px-2 text-slate-300">/</span>
                <span class="text-[#0B1F33]">{{ $hotelName }}</span>
            </nav>
        </div>
    </section>

    <section class="cct-shell py-8">
        @foreach ($warnings as $warning)
            <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900">{{ $warning }}</div>
        @endforeach

        @unless ($canonicalHotel || $hbxContentHotel)
            <div class="mb-4 rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm font-semibold text-blue-900">{{ __('public.details.unmapped_notice') }}</div>
        @endunless

        <div class="grid gap-8 lg:grid-cols-[1fr_360px]">
            <div>
                <div class="relative flex aspect-[16/7] min-h-64 items-end overflow-hidden rounded-3xl bg-[linear-gradient(135deg,#0B1F33,#0F766E)] p-6 text-white shadow-[0_24px_60px_rgba(11,31,51,0.18)]">
                    @if ($heroImageUrl)
                        <img src="{{ $heroImageUrl }}" alt="{{ $hotelName }}" class="absolute inset-0 h-full w-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-[#0B1F33]/95 via-[#0B1F33]/50 to-[#0B1F33]/10" aria-hidden="true"></div>
                    @else
                        <div class="cct-hero-pattern absolute inset-0 opacity-45" aria-hidden="true"></div>
                    @endif
                    <div class="relative">
                        <p class="text-sm font-bold text-teal-50">{{ __('public.brand') }}</p>
                        <h1 class="mt-2 text-4xl font-black leading-tight sm:text-5xl">{{ $hotelName }}</h1>
                    </div>
                </div>

                <p class="mt-5 text-base font-semibold text-slate-600">
                    {{ $locationLabel }}
                    @if ($canonicalHotel?->area) · {{ $canonicalHotel->area->name_en }} @endif
                    @if ($canonicalHotel?->star_rating ?? $hbxContentHotel?->star_rating ?? $result['star_rating'] ?? null) · {{ $canonicalHotel?->star_rating ?? $hbxContentHotel?->star_rating ?? $result['star_rating'] }} {{ __('public.results.stars') }} @endif
                </p>
                <p class="mt-4 max-w-3xl text-base leading-8 text-slate-700">{{ $description }}</p>

                <section class="mt-9">
                    <h2 class="text-2xl font-black text-[#0B1F33]">{{ __('public.details.rooms') }}</h2>
                    <div class="mt-5 grid gap-4">
                        @foreach ($rates as $rate)
                            <div class="cct-card p-5">
                                <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <span class="cct-badge {{ ($rate['requires_check_rate'] ?? false) ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                            {{ ($rate['requires_check_rate'] ?? false) ? __('public.booking.requires_recheck') : __('public.booking.bookable') }}
                                        </span>
                                        <h3 class="mt-3 text-lg font-black text-[#0B1F33]">{{ $rate['room_name'] }}</h3>
                                        <p class="mt-1 text-sm font-semibold text-slate-600">{{ str_replace('_', ' ', $rate['board_basis']) }} · {{ str_replace('_', ' ', $rate['refundability']) }}</p>
                                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ $rate['cancellation_summary'] }}</p>
                                        <p class="mt-2 text-sm font-semibold text-slate-500">{{ $rate['occupancy']['adults'] }} {{ __('public.search.adults') }} · {{ $rate['occupancy']['children'] }} {{ __('public.search.children') }}</p>
                                    </div>
                                    <div class="sm:text-end">
                                        <p class="text-3xl font-black text-[#0B1F33]">{{ $money->formatArray($rate['total']) }}</p>
                                        @if ($approximateEgp = $money->approximateEgpFromArray($rate['total']))
                                            <p class="mt-1 text-sm font-extrabold text-[#0F766E]">{{ $approximateEgp }}</p>
                                        @endif
                                        @if (isset($rate['public_rate_token'], $searchSession))
                                            <form method="POST" action="{{ route('rate-checks.store', ['locale' => app()->getLocale()]) }}" class="mt-3">
                                                @csrf
                                                <input type="hidden" name="search" value="{{ $searchSession->public_uuid }}">
                                                <input type="hidden" name="hotel" value="{{ $result['public_token'] }}">
                                                <input type="hidden" name="rate" value="{{ $rate['public_rate_token'] }}">
                                                <button type="submit" class="cct-button w-full sm:w-auto">{{ __('public.booking.check_rate') }}</button>
                                            </form>
                                        @else
                                            <button type="button" disabled class="mt-3 rounded-xl bg-slate-200 px-4 py-2 text-sm font-bold text-slate-600">{{ __('public.details.booking_disabled') }}</button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>

            <aside class="space-y-4">
                <div class="cct-card p-5">
                    <h2 class="font-black text-[#0B1F33]">{{ __('admin.facilities.plural_model_label') }}</h2>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @forelse ($facilities as $facility)
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">
                                @if (is_string($facility))
                                    {{ str_replace('_', ' ', $facility) }}
                                @elseif (method_exists($facility, 'translation'))
                                    {{ $facility->translation(app()->getLocale())?->name ?? $facility->code }}
                                @else
                                    {{ $facility->description ?: $facility->facility_code }}
                                @endif
                            </span>
                        @empty
                            <span class="text-sm text-slate-500">{{ __('public.cancellation.unknown') }}</span>
                        @endforelse
                    </div>
                </div>
                <div class="cct-card p-5">
                    <h2 class="font-black text-[#0B1F33]">{{ __('public.details.policies') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $canonicalHotel?->policy?->important_information ?? $canonicalHotel?->policy?->cancellation_notes ?? __('public.cancellation.unknown') }}</p>
                </div>
                <div class="cct-card p-5">
                    <h2 class="font-black text-[#0B1F33]">{{ __('public.details.map_placeholder') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $mapAddress }}</p>
                </div>
            </aside>
        </div>
    </section>
</x-layouts.public>
