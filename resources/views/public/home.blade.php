<x-layouts.public :meta-title="$metaTitle" :meta-description="$metaDescription">
    <section class="bg-teal-900 text-white">
        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
                <h1 class="text-3xl font-semibold sm:text-4xl">{{ __('public.home.title') }}</h1>
                <p class="mt-3 text-base text-teal-50">{{ __('public.home.subtitle') }}</p>
            </div>
            <div class="mt-8">
                <livewire:hotel-search-form :locale="app()->getLocale()" />
            </div>
        </div>
    </section>

    <section class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-3 lg:px-8">
        <div class="lg:col-span-2">
            <h2 class="text-xl font-semibold text-slate-900">{{ __('public.home.featured_destinations') }}</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @forelse ($featuredDestinations as $destination)
                    <a href="{{ route('hotels.search', ['destination' => $destination->token, 'check_in' => now()->addDays(7)->toDateString(), 'check_out' => now()->addDays(10)->toDateString(), 'rooms' => 1, 'adults' => 2, 'children' => 0, 'currency' => config('travel.currency.default'), 'locale' => app()->getLocale()]) }}" class="rounded border border-slate-200 bg-white p-4 hover:border-teal-600">
                        <span class="block font-medium text-slate-950">{{ $destination->label }}</span>
                        <span class="text-sm uppercase text-slate-500">{{ $destination->type }}</span>
                    </a>
                @empty
                    <p class="text-sm text-slate-600">{{ __('public.results.empty') }}</p>
                @endforelse
            </div>
        </div>
        <aside class="rounded border border-slate-200 bg-white p-5">
            <h2 class="text-xl font-semibold text-slate-900">{{ __('public.home.benefits') }}</h2>
            <p class="mt-3 text-sm leading-6 text-slate-700">{{ __('public.home.guidance') }}</p>
        </aside>
    </section>
</x-layouts.public>
