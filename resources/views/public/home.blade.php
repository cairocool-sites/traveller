<x-layouts.public :meta-title="$metaTitle" :meta-description="$metaDescription">
    <section class="cct-hero relative overflow-hidden text-white">
        <div class="cct-hero-pattern absolute inset-0 opacity-60" aria-hidden="true"></div>
        <div class="cct-shell relative pb-14 pt-10 sm:pt-12 lg:pb-20 lg:pt-11">
            <div class="max-w-4xl">
                <h1 class="text-3xl font-black leading-[1.08] tracking-normal sm:text-4xl lg:max-w-3xl lg:text-5xl">{{ __('public.home.title') }}</h1>
                <p class="mt-3 max-w-2xl text-base font-medium leading-7 text-teal-50 sm:text-lg">{{ __('public.home.subtitle') }}</p>
            </div>
            <div class="mt-5 grid max-w-2xl gap-2 text-sm font-semibold text-teal-50 sm:grid-cols-3">
                <span class="rounded-full border border-white/15 bg-white/10 px-4 py-2">{{ __('public.home.hero_point_1') }}</span>
                <span class="rounded-full border border-white/15 bg-white/10 px-4 py-2">{{ __('public.home.hero_point_2') }}</span>
                <span class="rounded-full border border-white/15 bg-white/10 px-4 py-2">{{ __('public.home.hero_point_3') }}</span>
            </div>
            <div class="relative z-10 mt-6 lg:-mb-32">
                <livewire:hotel-search-form :locale="app()->getLocale()" />
            </div>
        </div>
    </section>

    <section class="cct-shell pt-14 lg:pt-40">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-3xl font-black text-[#0B1F33]">{{ __('public.home.featured_destinations') }}</h2>
                <p class="mt-2 max-w-2xl text-base leading-7 text-slate-600">{{ __('public.home.popular_destinations_copy') }}</p>
            </div>
        </div>

        <div class="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @forelse ($featuredDestinations as $destination)
                <a href="{{ route('hotels.search', ['destination' => $destination->token, 'check_in' => now()->addDays(7)->toDateString(), 'check_out' => now()->addDays(10)->toDateString(), 'rooms' => 1, 'adults' => 2, 'children' => 0, 'currency' => config('travel.currency.default'), 'locale' => app()->getLocale()]) }}" class="group cct-card overflow-hidden transition hover:-translate-y-1 hover:border-[#14B8A6] hover:shadow-[0_24px_55px_rgba(11,31,51,0.14)]">
                    <div class="h-28 bg-[linear-gradient(135deg,#0B1F33,#0F766E)] p-4 text-white">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-white/15 text-lg font-black">{{ mb_substr($destination->label, 0, 1) }}</div>
                    </div>
                    <div class="p-5">
                        <span class="block text-lg font-black text-[#0B1F33]">{{ $destination->label }}</span>
                        <span class="mt-2 inline-flex rounded-full bg-[#F6F8FB] px-3 py-1 text-xs font-bold uppercase text-slate-600">{{ $destination->type }}</span>
                        <span class="mt-5 inline-flex items-center text-sm font-extrabold text-[#0F766E] group-hover:text-[#0B1F33]">
                            {{ __('public.results.view_hotel') }}
                        </span>
                    </div>
                </a>
            @empty
                <p class="text-sm text-slate-600">{{ __('public.results.empty') }}</p>
            @endforelse
        </div>
    </section>

    <section class="cct-shell py-16">
        <div class="grid gap-6 lg:grid-cols-3">
            <article class="cct-card p-7">
                <div class="mb-5 flex size-12 items-center justify-center rounded-2xl bg-[#14B8A6]/15 text-[#0F766E]">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 12h16M12 4v16" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                </div>
                <h2 class="text-xl font-black text-[#0B1F33]">{{ __('public.home.why_title') }}</h2>
                <p class="mt-3 leading-7 text-slate-600">{{ __('public.home.why_copy') }}</p>
            </article>
            <article class="cct-card p-7">
                <div class="mb-5 flex size-12 items-center justify-center rounded-2xl bg-[#C9A227]/15 text-[#8A6A12]">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3 5 6v5c0 4.2 2.7 7.9 7 10 4.3-2.1 7-5.8 7-10V6l-7-3Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /></svg>
                </div>
                <h2 class="text-xl font-black text-[#0B1F33]">{{ __('public.home.secure_title') }}</h2>
                <p class="mt-3 leading-7 text-slate-600">{{ __('public.home.secure_copy') }}</p>
            </article>
            <article class="cct-card p-7">
                <div class="mb-5 flex size-12 items-center justify-center rounded-2xl bg-[#0B1F33]/10 text-[#0B1F33]">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 17 17 6M8 6h9v9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </div>
                <h2 class="text-xl font-black text-[#0B1F33]">{{ __('public.home.reliable_title') }}</h2>
                <p class="mt-3 leading-7 text-slate-600">{{ __('public.home.reliable_copy') }}</p>
            </article>
        </div>
    </section>
</x-layouts.public>
