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
    $galleryImages = $hbxContentHotel?->images?->where('is_active', true)->sortBy([
        ['is_primary', 'desc'],
        ['sort_order', 'asc'],
    ])->values() ?? collect();
    $hbxPrimaryImage = $galleryImages->first();
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
        : ($hbxContentHotel?->facilities?->where('is_active', true)->take(10)->values() ?? collect($result['facilities'] ?? []));
    $rates = $result['rates'] ?? ($supplierDetails?->hotel->rooms ? collect($supplierDetails->hotel->rooms)->map(fn ($rate) => [
        'room_name' => $rate->roomName,
        'board_basis' => $rate->boardBasis->value,
        'total' => $rate->totalAmount->jsonSerialize(),
        'refundability' => $rate->refundability->value,
        'cancellation_summary' => app(\App\Services\PublicSearch\CancellationSummaryService::class)->summarize($rate->cancellationPolicies, app()->getLocale()),
        'occupancy' => $rate->occupancy->jsonSerialize(),
        'requires_check_rate' => (bool) ($rate->metadata['requires_check_rate'] ?? false),
    ])->all() : []);
    $bestRate = collect($rates)->sortBy('total.minor_amount')->first();

    $facilityLabel = function ($facility): string {
        if (is_string($facility)) {
            return str_replace('_', ' ', $facility);
        }

        if (method_exists($facility, 'translation')) {
            return $facility->translation(app()->getLocale())?->name ?? $facility->code;
        }

        return $facility->description ?: $facility->facility_code;
    };
@endphp
<x-layouts.public :meta-title="$metaTitle" :meta-description="$metaDescription">
    <section class="border-b border-slate-200 bg-white">
        <div class="cct-shell py-6">
            <nav class="text-sm font-semibold text-slate-500" aria-label="Breadcrumb">
                <a class="hover:text-[#0F766E]" href="{{ route('home', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.home') }}</a>
                <span class="px-2 text-slate-300">/</span>
                <a class="hover:text-[#0F766E]" href="{{ route('hotels.index', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.hotels') }}</a>
                <span class="px-2 text-slate-300">/</span>
                <span class="text-[#0B1F33]">{{ $hotelName }}</span>
            </nav>
        </div>
    </section>

    <section class="bg-[#FBF8F2] py-8">
        <div class="cct-shell">
            @foreach ($warnings as $warning)
                <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900">{{ $warning }}</div>
            @endforeach

            @unless ($canonicalHotel || $hbxContentHotel)
                <div class="mb-4 rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm font-semibold text-blue-900">{{ __('public.details.unmapped_notice') }}</div>
            @endunless

            <div class="overflow-hidden rounded-3xl bg-white shadow-[0_24px_70px_rgba(11,31,51,0.10)] ring-1 ring-slate-200">
                <div class="grid gap-1 p-3 sm:h-[340px] sm:grid-cols-[1.4fr_1fr_1fr] sm:grid-rows-2">
                    <div class="relative min-h-72 overflow-hidden rounded-2xl bg-[linear-gradient(135deg,#0B1F33,#0F766E)] sm:row-span-2">
                        @if ($heroImageUrl)
                            <img src="{{ $heroImageUrl }}" alt="{{ $hotelName }}" class="absolute inset-0 h-full w-full object-cover">
                            <div class="absolute inset-0 bg-gradient-to-t from-[#0B1F33]/90 via-[#0B1F33]/35 to-transparent" aria-hidden="true"></div>
                        @else
                            <div class="cct-hero-pattern absolute inset-0 opacity-50" aria-hidden="true"></div>
                        @endif
                        <div class="absolute inset-x-0 bottom-0 p-5 text-white">
                            <p class="text-sm font-bold text-teal-50">{{ __('public.brand') }}</p>
                            <h1 class="mt-2 max-w-3xl text-3xl font-black leading-tight sm:text-5xl">{{ $hotelName }}</h1>
                        </div>
                    </div>

                    @foreach ($galleryImages->slice(1, 3) as $image)
                        <div class="relative hidden overflow-hidden rounded-2xl bg-[#0B1F33] sm:block">
                            <img src="{{ $image->url('bigger') }}" alt="{{ $image->alt_text ?: $hotelName }}" class="h-full w-full object-cover">
                            <div class="absolute inset-0 bg-gradient-to-t from-[#0B1F33]/45 to-transparent" aria-hidden="true"></div>
                        </div>
                    @endforeach

                    <div class="relative hidden overflow-hidden rounded-2xl bg-[#0B1F33] sm:flex sm:items-center sm:justify-center">
                        @if ($galleryImages->count() > 4)
                            <img src="{{ $galleryImages->get(4)?->url('bigger') }}" alt="{{ $hotelName }}" class="absolute inset-0 h-full w-full object-cover">
                            <div class="absolute inset-0 bg-[#0B1F33]/65" aria-hidden="true"></div>
                            <div class="relative text-center text-white">
                                <div class="text-3xl font-black">+{{ max($galleryImages->count() - 4, 1) }}</div>
                                <div class="mt-1 text-sm font-bold">{{ app()->getLocale() === 'ar' ? 'صور' : 'Photos' }}</div>
                            </div>
                        @else
                            <div class="cct-hero-pattern absolute inset-0 opacity-30" aria-hidden="true"></div>
                            <span class="relative px-4 text-center text-sm font-bold text-teal-50">{{ __('public.catalogue.hotel_static_notice') }}</span>
                        @endif
                    </div>
                </div>

                <div class="grid gap-8 p-5 lg:grid-cols-[1fr_340px] lg:p-8">
                    <main>
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <h2 class="text-2xl font-black leading-tight text-[#0B1F33] sm:text-3xl">{{ $hotelName }}</h2>
                                    @if ($canonicalHotel?->star_rating ?? $hbxContentHotel?->star_rating ?? $result['star_rating'] ?? null)
                                        <span class="rounded-full bg-[#FBEEDE] px-3 py-1 text-sm font-black text-[#C9A227]">
                                            {{ str_repeat('★', (int) ($canonicalHotel?->star_rating ?? $hbxContentHotel?->star_rating ?? $result['star_rating'])) }}
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-2 text-sm font-bold text-slate-600">{{ $locationLabel }}</p>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-wrap gap-2">
                            @forelse ($facilities->take(6) as $facility)
                                <span class="rounded-xl bg-[#E1F0EE] px-3 py-2 text-xs font-extrabold text-[#0B3D3A]">{{ $facilityLabel($facility) }}</span>
                            @empty
                                <span class="rounded-xl bg-slate-100 px-3 py-2 text-xs font-extrabold text-slate-600">{{ __('public.cancellation.unknown') }}</span>
                            @endforelse
                        </div>

                        <div class="mt-7 max-w-4xl">
                            <h3 class="text-lg font-black text-[#0B1F33]">{{ __('public.details.meta_title') }}</h3>
                            <p class="mt-3 text-base leading-8 text-slate-700">{{ $description }}</p>
                        </div>

                        <section class="mt-9">
                            <h3 class="text-2xl font-black text-[#0B1F33]">{{ __('public.details.rooms') }}</h3>
                            <div class="mt-5 grid gap-4">
                                @foreach ($rates as $rate)
                                    <div class="rounded-2xl border border-slate-200 bg-[#F8FAFC] p-5">
                                        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <span class="cct-badge {{ ($rate['requires_check_rate'] ?? false) ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                                    {{ ($rate['requires_check_rate'] ?? false) ? __('public.booking.requires_recheck') : __('public.booking.bookable') }}
                                                </span>
                                                <h4 class="mt-3 text-lg font-black text-[#0B1F33]">{{ $rate['room_name'] }}</h4>
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
                    </main>

                    <aside class="space-y-4">
                        <div class="sticky top-24 rounded-3xl bg-[#0B3D3A] p-5 text-white shadow-[0_20px_50px_rgba(11,61,58,0.25)]">
                            @if ($bestRate)
                                <p class="text-sm font-bold text-[#B9CFC9]">{{ __('public.price.from') }}</p>
                                <p class="mt-1 text-3xl font-black">{{ $money->formatArray($bestRate['total']) }}</p>
                                @if ($approximateEgp = $money->approximateEgpFromArray($bestRate['total']))
                                    <p class="mt-1 text-sm font-extrabold text-[#14B8A6]">{{ $approximateEgp }}</p>
                                @endif
                            @else
                                <p class="text-xl font-black">{{ __('public.price.unavailable') }}</p>
                            @endif

                            @if ($searchSession)
                                <div class="mt-5 grid grid-cols-2 gap-2">
                                    <div class="rounded-xl bg-white/10 p-3">
                                        <p class="text-xs font-bold text-[#B9CFC9]">{{ __('public.booking.check_in') }}</p>
                                        <p class="mt-1 text-sm font-black">{{ $searchSession->check_in->format('Y-m-d') }}</p>
                                    </div>
                                    <div class="rounded-xl bg-white/10 p-3">
                                        <p class="text-xs font-bold text-[#B9CFC9]">{{ __('public.booking.check_out') }}</p>
                                        <p class="mt-1 text-sm font-black">{{ $searchSession->check_out->format('Y-m-d') }}</p>
                                    </div>
                                </div>
                            @endif

                            @if ($bestRate && isset($bestRate['public_rate_token'], $searchSession))
                                <form method="POST" action="{{ route('rate-checks.store', ['locale' => app()->getLocale()]) }}" class="mt-5">
                                    @csrf
                                    <input type="hidden" name="search" value="{{ $searchSession->public_uuid }}">
                                    <input type="hidden" name="hotel" value="{{ $result['public_token'] }}">
                                    <input type="hidden" name="rate" value="{{ $bestRate['public_rate_token'] }}">
                                    <button type="submit" class="w-full rounded-xl bg-[#E8623D] px-5 py-3 text-sm font-black text-white shadow-lg shadow-orange-900/20 transition hover:bg-[#d95431] focus:outline-none focus:ring-4 focus:ring-[#E8623D]/30">{{ __('public.booking.check_rate') }}</button>
                                </form>
                            @endif
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white p-5">
                            <h3 class="font-black text-[#0B1F33]">{{ __('public.details.policies') }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $canonicalHotel?->policy?->important_information ?? $canonicalHotel?->policy?->cancellation_notes ?? __('public.cancellation.unknown') }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-5">
                            <h3 class="font-black text-[#0B1F33]">{{ __('public.details.map_placeholder') }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $mapAddress }}</p>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
