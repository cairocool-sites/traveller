@php($money = app(\App\Services\PublicSearch\MoneyFormatter::class))
<x-layouts.public :meta-title="$metaTitle" :meta-description="$metaDescription">
    <section class="border-b border-slate-200 bg-white">
        <div class="cct-shell py-8">
            <nav class="text-sm font-semibold text-slate-500" aria-label="Breadcrumb">
                <a class="hover:text-[#0F766E]" href="{{ route('home', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.home') }}</a>
                <span class="px-2 text-slate-300">/</span>
                <span class="text-[#0B1F33]">{{ __('public.results.title') }}</span>
            </nav>
            <div class="mt-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="text-3xl font-black text-[#0B1F33] sm:text-4xl">{{ __('public.results.title') }}</h1>
                    <p class="mt-2 text-base font-semibold text-slate-600">{{ $searchSession->destination_label }} · {{ $searchSession->check_in->toDateString() }} - {{ $searchSession->check_out->toDateString() }}</p>
                </div>
                <a href="{{ route('hotels.index', ['locale' => app()->getLocale()]) }}" class="cct-button-navy">{{ __('public.results.new_search') }}</a>
            </div>
        </div>
    </section>

    <section class="cct-shell py-8">
        @if ($searchSession->warnings)
            <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm font-semibold text-amber-900">
                <p class="font-black">{{ __('public.results.warnings') }}</p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach ($searchSession->warnings as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="GET" action="{{ route('hotels.search') }}" class="cct-card grid gap-3 p-4 md:grid-cols-6">
            <input type="hidden" name="session" value="{{ $searchSession->public_uuid }}">
            <input type="hidden" name="locale" value="{{ app()->getLocale() }}">
            <label class="md:col-span-2">
                <span class="cct-label">{{ __('public.results.filter_name') }}</span>
                <input name="name" value="{{ $filters['name'] ?? '' }}" class="cct-input" placeholder="{{ __('public.results.filter_name') }}">
            </label>
            <label>
                <span class="cct-label">{{ __('public.results.star_rating') }}</span>
                <select name="star_rating" class="cct-input">
                    <option value="">{{ __('public.results.any') }}</option>
                    @foreach ([5,4,3,2,1] as $star)
                        <option value="{{ $star }}" @selected(($filters['star_rating'] ?? '') == $star)>{{ $star }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="cct-label">{{ __('public.results.refundability') }}</span>
                <select name="refundability" class="cct-input">
                    <option value="">{{ __('public.results.any') }}</option>
                    <option value="refundable" @selected(($filters['refundability'] ?? '') === 'refundable')>{{ __('public.results.refundable') }}</option>
                    <option value="non_refundable" @selected(($filters['refundability'] ?? '') === 'non_refundable')>{{ __('public.results.non_refundable') }}</option>
                </select>
            </label>
            <label>
                <span class="cct-label">{{ __('public.results.sort') }}</span>
                <select name="sort" class="cct-input">
                    <option value="recommended">{{ __('public.results.recommended') }}</option>
                    <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>{{ __('public.results.price_asc') }}</option>
                    <option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>{{ __('public.results.price_desc') }}</option>
                    <option value="star_rating" @selected(($filters['sort'] ?? '') === 'star_rating')>{{ __('public.results.star_rating') }}</option>
                </select>
            </label>
            <div class="flex items-end">
                <button class="cct-button w-full">{{ __('public.results.apply') }}</button>
            </div>
        </form>

        <div class="mt-7 grid gap-5">
            @forelse ($results as $hotel)
                @php($firstRate = $hotel['rates'][0] ?? null)
                <article class="cct-card overflow-hidden p-4 sm:p-5">
                    <div class="grid gap-5 lg:grid-cols-[220px_1fr_240px]">
                        <div class="flex min-h-44 items-end rounded-2xl bg-[linear-gradient(135deg,#0B1F33,#0F766E)] p-5 text-white">
                            <div>
                                <div class="mb-3 flex gap-1 text-[#C9A227]" aria-label="{{ $hotel['star_rating'] ?? 0 }} stars">
                                    @for ($i = 0; $i < (int) ($hotel['star_rating'] ?? 0); $i++)
                                        <span>★</span>
                                    @endfor
                                </div>
                                <p class="text-sm font-bold text-teal-50">{{ $hotel['location'] }}</p>
                            </div>
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($firstRate)
                                    <span class="cct-badge {{ $firstRate['requires_check_rate'] ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                        {{ $firstRate['requires_check_rate'] ? __('public.booking.requires_recheck') : __('public.booking.bookable') }}
                                    </span>
                                @endif
                                @if ($hotel['star_rating'])
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{{ $hotel['star_rating'] }} {{ __('public.results.stars') }}</span>
                                @endif
                            </div>
                            <h2 class="mt-3 text-2xl font-black leading-tight text-[#0B1F33]">{{ $hotel['name'] }}</h2>
                            <p class="mt-2 text-sm font-semibold text-slate-600">{{ $hotel['location'] }}</p>

                            @if ($firstRate)
                                <div class="mt-5 rounded-2xl border border-slate-200 bg-[#F6F8FB] p-4">
                                    <p class="font-black text-[#0B1F33]">{{ $firstRate['room_name'] }}</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-700">{{ str_replace('_', ' ', $firstRate['board_basis']) }} · {{ str_replace('_', ' ', $firstRate['refundability']) }}</p>
                                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $firstRate['cancellation_summary'] }}</p>
                                    @foreach (array_slice($hotel['rates'], 1, 1) as $extraRate)
                                        <span class="mt-3 inline-flex cct-badge {{ $extraRate['requires_check_rate'] ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                            {{ $extraRate['requires_check_rate'] ? __('public.booking.requires_recheck') : __('public.booking.bookable') }}
                                        </span>
                                        <p class="mt-3 border-t border-slate-200 pt-3 text-sm font-semibold text-slate-700">{{ str_replace('_', ' ', $extraRate['board_basis']) }} · {{ str_replace('_', ' ', $extraRate['refundability']) }}</p>
                                        <p class="mt-1 text-sm leading-6 text-slate-600">{{ $extraRate['cancellation_summary'] }}</p>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-4 lg:items-end">
                            <div class="lg:text-end">
                                <p class="text-sm font-bold text-slate-500">{{ __('public.price.from') }}</p>
                                <p class="mt-1 text-3xl font-black text-[#0B1F33]">{{ $money->formatArray($hotel['minimum_price']) }}</p>
                                <p class="mt-2 text-xs font-semibold text-slate-500">{{ $hotel['taxes_known'] ? __('public.results.taxes_known') : __('public.results.taxes_unknown') }}</p>
                            </div>
                            <a class="cct-button mt-5 w-full" href="{{ route('hotels.show', ['hotel' => $hotel['public_token'], 'search' => $searchSession->public_uuid, 'locale' => app()->getLocale()]) }}">
                                {{ __('public.results.view_hotel') }}
                            </a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="cct-card p-10 text-center">
                    <div class="mx-auto mb-5 flex size-14 items-center justify-center rounded-2xl bg-[#14B8A6]/15 text-[#0F766E]">
                        <svg class="size-7" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M10.8 18a7.2 7.2 0 1 1 0-14.4 7.2 7.2 0 0 1 0 14.4ZM21 21l-4.3-4.3" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    </div>
                    <h2 class="text-2xl font-black text-[#0B1F33]">{{ __('public.results.empty_title') }}</h2>
                    <p class="mx-auto mt-3 max-w-xl leading-7 text-slate-600">{{ __('public.results.empty') }}</p>
                    <a class="cct-button mt-6" href="{{ route('hotels.index', ['locale' => app()->getLocale()]) }}">{{ __('public.results.new_search') }}</a>
                </div>
            @endforelse
        </div>
    </section>
</x-layouts.public>
