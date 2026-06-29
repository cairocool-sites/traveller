@php
    $name = app()->getLocale() === 'ar'
        ? ($destination->name_ar ?: $destination->destination_name)
        : ($destination->name_en ?: $destination->destination_name);
@endphp
<x-layouts.public :meta-title="$metaTitle" :meta-description="$metaDescription">
    <script type="application/ld+json">@json($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>

    <section class="border-b border-slate-200 bg-white">
        <div class="cct-shell py-8">
            <nav class="text-sm font-semibold text-slate-500" aria-label="Breadcrumb">
                <a class="hover:text-[#0F766E]" href="{{ route('home', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.home') }}</a>
                <span class="px-2 text-slate-300">/</span>
                <span class="text-[#0B1F33]">{{ $name }}</span>
            </nav>
            <div class="mt-8 max-w-3xl">
                <p class="text-sm font-black uppercase tracking-wide text-[#0F766E]">{{ __('public.catalogue.local_catalogue') }}</p>
                <h1 class="mt-3 text-4xl font-black leading-tight text-[#0B1F33] sm:text-5xl">{{ $name }}</h1>
                <p class="mt-4 text-base leading-8 text-slate-600">{{ __('public.catalogue.destination_intro', ['destination' => $name]) }}</p>
            </div>
        </div>
    </section>

    <section class="cct-shell py-10">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-2xl font-black text-[#0B1F33]">{{ __('public.catalogue.hotels_in_destination', ['destination' => $name]) }}</h2>
                <p class="mt-2 text-sm font-semibold text-slate-600">{{ __('public.catalogue.live_rates_notice') }}</p>
            </div>
            <a class="cct-button" href="{{ route('hotels.index', ['locale' => app()->getLocale()]) }}">{{ __('public.search.submit') }}</a>
        </div>

        <div class="mt-7 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($hotels as $hotel)
                @php
                    $hotelName = app()->getLocale() === 'ar'
                        ? ($hotel->name_ar ?: $hotel->hotel_name)
                        : ($hotel->name_en ?: $hotel->hotel_name);
                    $image = $hotel->images->where('is_active', true)->sortBy('sort_order')->first();
                @endphp
                <article class="cct-card overflow-hidden">
                    @if ($image?->path)
                        <img class="h-44 w-full object-cover" src="{{ $image->path }}" alt="{{ $image->alt_text ?: $hotelName }}" loading="lazy">
                    @else
                        <div class="h-44 bg-[linear-gradient(135deg,#0B1F33,#0F766E)]"></div>
                    @endif
                    <div class="p-5">
                        <p class="text-xs font-black uppercase tracking-wide text-[#0F766E]">{{ $hotel->category_code ?: __('public.results.stars') }}</p>
                        <h3 class="mt-2 text-xl font-black text-[#0B1F33]">{{ $hotelName }}</h3>
                        <p class="mt-2 line-clamp-2 text-sm leading-6 text-slate-600">{{ $hotel->address ?: $name }}</p>
                        <a class="mt-5 inline-flex font-black text-[#0F766E] hover:text-[#0B1F33]" href="{{ route('catalogue.hotels.show', ['destination' => $destination->slug, 'hotel' => $hotel->slug, 'locale' => app()->getLocale()]) }}">
                            {{ __('public.results.view_hotel') }}
                        </a>
                    </div>
                </article>
            @empty
                <div class="cct-card p-6 text-sm font-semibold text-slate-600">{{ __('public.catalogue.no_public_hotels') }}</div>
            @endforelse
        </div>
    </section>
</x-layouts.public>
