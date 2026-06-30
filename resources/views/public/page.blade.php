<x-layouts.public :meta-title="$metaTitle" :meta-description="$metaDescription">
    <section class="bg-white">
        <div class="cct-shell py-12 lg:py-16">
            <nav class="mb-6 flex items-center gap-2 text-sm font-semibold text-slate-500" aria-label="{{ __('public.nav.primary') }}">
                <a class="hover:text-[#0F766E]" href="{{ route('home', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.home') }}</a>
                <span>/</span>
                <span class="text-[#0B1F33]">{{ __('public.pages.'.$pageKey.'.title') }}</span>
            </nav>

            <div class="max-w-3xl">
                <p class="text-sm font-black uppercase tracking-wide text-[#0F766E]">{{ __('public.pages.eyebrow') }}</p>
                <h1 class="mt-3 text-4xl font-black leading-tight text-[#0B1F33] sm:text-5xl">{{ __('public.pages.'.$pageKey.'.title') }}</h1>
                <p class="mt-5 text-lg font-medium leading-8 text-slate-600">{{ __('public.pages.'.$pageKey.'.intro') }}</p>
            </div>
        </div>
    </section>

    <section class="cct-shell py-10">
        <div class="grid gap-5 lg:grid-cols-3">
            @foreach (__('public.pages.'.$pageKey.'.sections') as $section)
                <article class="cct-card p-6">
                    <h2 class="text-xl font-black text-[#0B1F33]">{{ $section['title'] }}</h2>
                    <p class="mt-3 leading-7 text-slate-600">{{ $section['body'] }}</p>
                </article>
            @endforeach
        </div>

        <div class="mt-8 rounded-2xl border border-[#14B8A6]/30 bg-[#14B8A6]/10 p-5 text-sm font-semibold leading-7 text-[#0B1F33]">
            {{ __('public.pages.safe_notice') }}
        </div>
    </section>
</x-layouts.public>
