<x-layouts.public :meta-title="$metaTitle" :meta-description="$metaDescription">
    <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-semibold text-slate-950">{{ __('public.search.title') }}</h1>
        <p class="mt-2 max-w-2xl text-slate-600">{{ __('public.home.guidance') }}</p>
        <div class="mt-6">
            <livewire:hotel-search-form :locale="app()->getLocale()" />
        </div>
    </section>
</x-layouts.public>
