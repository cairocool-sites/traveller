<x-layouts.public :meta-title="$metaTitle" :meta-description="$metaDescription">
    <section class="cct-shell py-8">
        <nav class="text-sm font-semibold text-slate-500" aria-label="Breadcrumb">
            <a class="hover:text-[#0F766E]" href="{{ route('home', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.home') }}</a>
            <span class="px-2 text-slate-300">/</span>
            <a class="hover:text-[#0F766E]" href="{{ route('hotels.index', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.hotels') }}</a>
            <span class="px-2 text-slate-300">/</span>
            <span class="text-[#0B1F33]">{{ __('public.booking.guest_details_title') }}</span>
        </nav>

        <div class="mt-7 grid gap-6 lg:grid-cols-[1fr_360px]">
            <div class="cct-card p-6 sm:p-8">
                <span class="cct-badge bg-[#14B8A6]/15 text-[#0F766E]">{{ __('public.booking.rate_confirmed') }}</span>
                <h1 class="mt-4 text-3xl font-black text-[#0B1F33]">{{ __('public.booking.guest_details_title') }}</h1>
                <p class="mt-3 text-base leading-7 text-slate-600">{{ __('public.booking.checkrate_notice') }}</p>

                @if ($rateCheck->price_changed)
                    <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900">
                        {{ __('public.booking.price_changed') }}
                    </div>
                @endif

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-[#F6F8FB] p-5">
                        <p class="text-sm font-bold text-slate-500">{{ __('public.details.rooms') }}</p>
                        <p class="mt-2 text-lg font-black text-[#0B1F33]">{{ $rateCheck->room_snapshot['room_name'] }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-[#F6F8FB] p-5">
                        <p class="text-sm font-bold text-slate-500">{{ __('public.booking.confirmed_price') }}</p>
                        <p class="mt-2 text-3xl font-black text-[#0B1F33]">{{ $money->formatMinor($rateCheck->checked_amount_minor ?? $rateCheck->original_amount_minor, $rateCheck->currency->code) }}</p>
                        @if ($approximateEgp = $money->approximateEgpFromMinor($rateCheck->checked_amount_minor ?? $rateCheck->original_amount_minor, $rateCheck->currency->code))
                            <p class="mt-1 text-sm font-extrabold text-[#0F766E]">{{ $approximateEgp }}</p>
                        @endif
                    </div>
                </div>

                <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 class="text-lg font-black text-[#0B1F33]">{{ __('public.booking.conditions_title') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        {{ $rateCheck->room_snapshot['cancellation_summary'] ?? __('public.cancellation.unknown') }}
                    </p>
                    <p class="mt-3 text-sm font-semibold text-slate-600">{{ __('public.booking.not_completed_notice') }}</p>
                </div>

                <livewire:guest-details-form :rate-check-uuid="$rateCheck->public_uuid" />
            </div>

            <aside class="cct-card h-fit p-6">
                <h2 class="text-lg font-black text-[#0B1F33]">{{ __('public.booking.safe_next_steps') }}</h2>
                <ul class="mt-4 space-y-3 text-sm font-semibold text-slate-600">
                    <li>{{ __('public.booking.safe_step_1') }}</li>
                    <li>{{ __('public.booking.safe_step_2') }}</li>
                    <li>{{ __('public.booking.safe_step_3') }}</li>
                </ul>
            </aside>
        </div>
    </section>
</x-layouts.public>
