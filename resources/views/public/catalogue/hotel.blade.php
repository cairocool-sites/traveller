@php
    $destinationName = app()->getLocale() === 'ar'
        ? ($destination->name_ar ?: $destination->destination_name)
        : ($destination->name_en ?: $destination->destination_name);
    $hotelName = app()->getLocale() === 'ar'
        ? ($hotel->name_ar ?: $hotel->hotel_name)
        : ($hotel->name_en ?: $hotel->hotel_name);
    $description = $hotel->translations->firstWhere('locale', app()->getLocale())?->description
        ?? $hotel->translations->first()?->description
        ?? __('public.catalogue.hotel_static_notice');
    $primaryImage = $hotel->images->where('is_active', true)->sortBy('sort_order')->first();
@endphp
<x-layouts.public :meta-title="$metaTitle" :meta-description="$metaDescription">
    <script type="application/ld+json">@json($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>

    <section class="border-b border-slate-200 bg-white">
        <div class="cct-shell py-8">
            <nav class="text-sm font-semibold text-slate-500" aria-label="Breadcrumb">
                <a class="hover:text-[#0F766E]" href="{{ route('home', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.home') }}</a>
                <span class="px-2 text-slate-300">/</span>
                <a class="hover:text-[#0F766E]" href="{{ route('catalogue.destinations.show', ['destination' => $destination->slug, 'locale' => app()->getLocale()]) }}">{{ $destinationName }}</a>
                <span class="px-2 text-slate-300">/</span>
                <span class="text-[#0B1F33]">{{ $hotelName }}</span>
            </nav>
        </div>
    </section>

    <section class="cct-shell py-10">
        <div class="grid gap-8 lg:grid-cols-[1fr_360px]">
            <div>
                @if ($primaryImage?->path)
                    <img class="aspect-[16/7] min-h-64 w-full rounded-3xl object-cover shadow-[0_24px_60px_rgba(11,31,51,0.16)]" src="{{ $primaryImage->path }}" alt="{{ $primaryImage->alt_text ?: $hotelName }}" loading="lazy">
                @else
                    <div class="relative flex aspect-[16/7] min-h-64 items-end overflow-hidden rounded-3xl bg-[linear-gradient(135deg,#0B1F33,#0F766E)] p-6 text-white shadow-[0_24px_60px_rgba(11,31,51,0.16)]">
                        <div class="cct-hero-pattern absolute inset-0 opacity-45" aria-hidden="true"></div>
                    </div>
                @endif

                <p class="mt-7 text-sm font-black uppercase tracking-wide text-[#0F766E]">{{ $destinationName }}</p>
                <h1 class="mt-2 text-4xl font-black leading-tight text-[#0B1F33] sm:text-5xl">{{ $hotelName }}</h1>
                <p class="mt-4 text-base font-semibold text-slate-600">
                    {{ $hotel->category_code ?: __('public.results.stars') }}
                    @if ($hotel->address) · {{ $hotel->address }} @endif
                </p>
                <p class="mt-5 max-w-3xl text-base leading-8 text-slate-700">{{ $description }}</p>

                <section class="mt-9">
                    <h2 class="text-2xl font-black text-[#0B1F33]">{{ __('public.details.rooms') }}</h2>
                    <div class="mt-5 grid gap-4">
                        @forelse ($hotel->rooms->where('is_active', true)->sortBy('sort_order') as $room)
                            <div class="cct-card p-5">
                                <h3 class="text-lg font-black text-[#0B1F33]">{{ $room->room_name ?: $room->room_code }}</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('public.catalogue.static_room_notice') }}</p>
                            </div>
                        @empty
                            <div class="cct-card p-5 text-sm font-semibold text-slate-600">{{ __('public.catalogue.no_room_content') }}</div>
                        @endforelse
                    </div>
                </section>
            </div>

            <aside class="space-y-4">
                <div class="cct-card p-5">
                    <h2 class="font-black text-[#0B1F33]">{{ __('admin.facilities.plural_model_label') }}</h2>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @forelse ($hotel->facilities->where('is_active', true)->take(12) as $facility)
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{{ $facility->description ?: $facility->facility_code }}</span>
                        @empty
                            <span class="text-sm text-slate-500">{{ __('public.cancellation.unknown') }}</span>
                        @endforelse
                    </div>
                </div>
                <div class="cct-card p-5">
                    <h2 class="font-black text-[#0B1F33]">{{ __('public.catalogue.live_rates_title') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('public.catalogue.live_rates_notice') }}</p>
                    <a class="cct-button mt-5 w-full" href="{{ route('hotels.index', ['locale' => app()->getLocale()]) }}">{{ __('public.search.submit') }}</a>
                </div>
            </aside>
        </div>
    </section>
</x-layouts.public>
