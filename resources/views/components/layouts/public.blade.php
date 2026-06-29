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
    <body class="min-h-screen bg-slate-50 text-slate-950 antialiased">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('home', ['locale' => app()->getLocale()]) }}" class="text-xl font-semibold tracking-normal text-teal-800">
                    {{ __('public.brand') }}
                </a>
                <nav class="flex flex-wrap items-center gap-4 text-sm font-medium text-slate-700">
                    <a class="hover:text-teal-700" href="{{ route('home', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.home') }}</a>
                    <a class="hover:text-teal-700" href="{{ route('hotels.index', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.hotels') }}</a>
                    <a class="hover:text-teal-700" href="#about">{{ __('public.nav.about') }}</a>
                    <a class="hover:text-teal-700" href="#contact">{{ __('public.nav.contact') }}</a>
                </nav>
                <div class="flex items-center gap-2 text-sm">
                    <a class="rounded border border-slate-300 px-3 py-1 hover:bg-slate-100" href="{{ request()->fullUrlWithQuery(['locale' => 'ar']) }}">AR</a>
                    <a class="rounded border border-slate-300 px-3 py-1 hover:bg-slate-100" href="{{ request()->fullUrlWithQuery(['locale' => 'en']) }}">EN</a>
                </div>
            </div>
        </header>

        <main>
            {{ $slot }}
        </main>

        <footer id="contact" class="mt-16 border-t border-slate-200 bg-white">
            <div class="mx-auto grid max-w-7xl gap-4 px-4 py-8 text-sm text-slate-600 sm:px-6 md:grid-cols-3 lg:px-8">
                <div>
                    <p class="font-semibold text-slate-900">{{ __('public.brand') }}</p>
                    <p class="mt-2">{{ __('public.layout.footer_note') }}</p>
                </div>
                <div id="about">
                    <p class="font-semibold text-slate-900">{{ __('public.nav.about') }}</p>
                    <p class="mt-2">{{ __('public.home.guidance') }}</p>
                </div>
                <div>
                    <p class="font-semibold text-slate-900">{{ __('public.nav.contact') }}</p>
                    <p class="mt-2">{{ __('public.layout.contact_placeholder') }}</p>
                </div>
            </div>
        </footer>
        @livewireScripts
    </body>
</html>
