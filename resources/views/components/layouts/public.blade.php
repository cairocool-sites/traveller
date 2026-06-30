@props(['metaTitle' => config('app.name'), 'metaDescription' => ''])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $metaTitle }}</title>
        <meta name="description" content="{{ $metaDescription }}">
        <link rel="canonical" href="{{ url()->current() }}">
        <meta property="og:title" content="{{ $metaTitle }}">
        <meta property="og:description" content="{{ $metaDescription }}">
        <meta property="og:type" content="website">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-[#F6F8FB] text-[#0B1F33] antialiased">
        <header class="sticky top-0 z-40 border-b border-white/10 bg-[#0B1F33]/95 text-white shadow-[0_10px_30px_rgba(11,31,51,0.18)] backdrop-blur">
            <div class="cct-shell flex min-h-20 items-center justify-between gap-4">
                <a href="{{ route('home', ['locale' => app()->getLocale()]) }}" class="flex items-center gap-3">
                    <span class="flex size-11 items-center justify-center rounded-2xl bg-[#14B8A6] font-black text-[#0B1F33] shadow-lg">CC</span>
                    <span>
                        <span class="block text-lg font-black leading-tight tracking-normal">{{ __('public.brand') }}</span>
                        <span class="block text-xs font-semibold text-teal-100">{{ __('public.layout.wordmark_subtitle') }}</span>
                    </span>
                </a>

                <nav class="hidden items-center gap-7 text-sm font-bold text-slate-100 lg:flex" aria-label="{{ __('public.nav.primary') }}">
                    <a class="transition hover:text-[#14B8A6]" href="{{ route('home', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.home') }}</a>
                    <a class="transition hover:text-[#14B8A6]" href="{{ route('hotels.index', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.hotels') }}</a>
                    <a class="transition hover:text-[#14B8A6]" href="{{ route('pages.about', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.about') }}</a>
                    <a class="transition hover:text-[#14B8A6]" href="{{ route('pages.contact', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.contact') }}</a>
                </nav>

                <div class="hidden items-center gap-3 lg:flex">
                    <div class="flex rounded-full border border-white/15 bg-white/10 p-1 text-xs font-black" aria-label="{{ __('public.nav.language') }}">
                        <a class="rounded-full px-3 py-1.5 transition {{ app()->getLocale() === 'ar' ? 'bg-white text-[#0B1F33]' : 'text-white hover:bg-white/10' }}" href="{{ request()->fullUrlWithQuery(['locale' => 'ar']) }}">AR</a>
                        <a class="rounded-full px-3 py-1.5 transition {{ app()->getLocale() === 'en' ? 'bg-white text-[#0B1F33]' : 'text-white hover:bg-white/10' }}" href="{{ request()->fullUrlWithQuery(['locale' => 'en']) }}">EN</a>
                    </div>
                </div>

                <details class="relative lg:hidden">
                    <summary class="flex size-11 cursor-pointer list-none items-center justify-center rounded-xl border border-white/15 bg-white/10 text-white" aria-label="{{ __('public.nav.menu') }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        </svg>
                    </summary>
                    <div class="absolute end-0 mt-3 w-72 rounded-2xl border border-slate-200 bg-white p-3 text-[#0B1F33] shadow-2xl">
                        <a class="block rounded-xl px-4 py-3 text-sm font-bold hover:bg-slate-50" href="{{ route('home', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.home') }}</a>
                        <a class="block rounded-xl px-4 py-3 text-sm font-bold hover:bg-slate-50" href="{{ route('hotels.index', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.hotels') }}</a>
                        <a class="block rounded-xl px-4 py-3 text-sm font-bold hover:bg-slate-50" href="{{ route('pages.about', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.about') }}</a>
                        <a class="block rounded-xl px-4 py-3 text-sm font-bold hover:bg-slate-50" href="{{ route('pages.contact', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.contact') }}</a>
                        <div class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1 text-center text-xs font-black">
                            <a class="rounded-lg px-3 py-2 {{ app()->getLocale() === 'ar' ? 'bg-white text-[#0B1F33] shadow-sm' : 'text-slate-700' }}" href="{{ request()->fullUrlWithQuery(['locale' => 'ar']) }}">AR</a>
                            <a class="rounded-lg px-3 py-2 {{ app()->getLocale() === 'en' ? 'bg-white text-[#0B1F33] shadow-sm' : 'text-slate-700' }}" href="{{ request()->fullUrlWithQuery(['locale' => 'en']) }}">EN</a>
                        </div>
                    </div>
                </details>
            </div>
        </header>

        <main>
            {{ $slot }}
        </main>

        <footer id="contact" class="mt-20 border-t border-slate-200 bg-white">
            <div class="cct-shell grid gap-8 py-10 text-sm text-slate-600 md:grid-cols-3">
                <div>
                    <p class="text-base font-black text-[#0B1F33]">{{ __('public.brand') }}</p>
                    <p class="mt-3 leading-6">{{ __('public.layout.footer_note') }}</p>
                </div>
                <div>
                    <p class="font-black text-[#0B1F33]">{{ __('public.nav.about') }}</p>
                    <div class="mt-3 grid gap-2">
                        <a class="hover:text-[#0F766E]" href="{{ route('pages.about', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.about') }}</a>
                        <a class="hover:text-[#0F766E]" href="{{ route('pages.terms', ['locale' => app()->getLocale()]) }}">{{ __('public.pages.terms.title') }}</a>
                        <a class="hover:text-[#0F766E]" href="{{ route('pages.privacy', ['locale' => app()->getLocale()]) }}">{{ __('public.pages.privacy.title') }}</a>
                    </div>
                </div>
                <div>
                    <p class="font-black text-[#0B1F33]">{{ __('public.nav.contact') }}</p>
                    <div class="mt-3 grid gap-2">
                        <a class="hover:text-[#0F766E]" href="{{ route('pages.contact', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.contact') }}</a>
                        <a class="hover:text-[#0F766E]" href="{{ route('pages.support', ['locale' => app()->getLocale()]) }}">{{ __('public.pages.support.title') }}</a>
                        <a class="hover:text-[#0F766E]" href="{{ route('pages.payment-policy', ['locale' => app()->getLocale()]) }}">{{ __('public.pages.payment-policy.title') }}</a>
                        <a class="hover:text-[#0F766E]" href="{{ route('pages.cancellation-policy', ['locale' => app()->getLocale()]) }}">{{ __('public.pages.cancellation-policy.title') }}</a>
                    </div>
                </div>
            </div>
        </footer>
        @livewireScripts
    </body>
</html>
