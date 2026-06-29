<x-layouts.public :meta-title="$metaTitle" :meta-description="$metaDescription">
    <section class="cct-hero relative overflow-hidden text-white">
        <div class="cct-hero-pattern absolute inset-0 opacity-50" aria-hidden="true"></div>
        <div class="cct-shell relative py-14 sm:py-20">
            <h1 class="max-w-3xl text-4xl font-black leading-tight sm:text-5xl">{{ __('public.search.title') }}</h1>
            <p class="mt-4 max-w-2xl text-lg font-medium leading-8 text-teal-50">{{ __('public.home.guidance') }}</p>
        </div>
    </section>

    <section class="cct-shell -mt-10 pb-16">
        <div class="relative z-10">
            <livewire:hotel-search-form :locale="app()->getLocale()" />
        </div>
    </section>
</x-layouts.public>
