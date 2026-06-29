@php($money = app(\App\Services\PublicSearch\MoneyFormatter::class))
<x-layouts.public :meta-title="$metaTitle" :meta-description="$metaDescription">
    <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <nav class="text-sm text-slate-500" aria-label="Breadcrumb">
            <a href="{{ route('home', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.home') }}</a>
            <span>/</span>
            <span>{{ __('public.results.title') }}</span>
        </nav>
        <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-3xl font-semibold text-slate-950">{{ __('public.results.title') }}</h1>
                <p class="mt-1 text-slate-600">{{ $searchSession->destination_label }} · {{ $searchSession->check_in->toDateString() }} - {{ $searchSession->check_out->toDateString() }}</p>
            </div>
        </div>

        @if ($searchSession->warnings)
            <div class="mt-5 rounded border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <p class="font-medium">{{ __('public.results.warnings') }}</p>
                <ul class="mt-2 list-inside list-disc">
                    @foreach ($searchSession->warnings as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="GET" action="{{ route('hotels.search') }}" class="mt-6 grid gap-3 rounded border border-slate-200 bg-white p-4 md:grid-cols-6">
            <input type="hidden" name="session" value="{{ $searchSession->public_uuid }}">
            <input type="hidden" name="locale" value="{{ app()->getLocale() }}">
            <input name="name" value="{{ $filters['name'] ?? '' }}" class="rounded border border-slate-300 px-3 py-2" placeholder="{{ __('public.results.filter_name') }}">
            <select name="star_rating" class="rounded border border-slate-300 px-3 py-2">
                <option value="">{{ __('public.results.star_rating') }}</option>
                @foreach ([5,4,3,2,1] as $star)
                    <option value="{{ $star }}" @selected(($filters['star_rating'] ?? '') == $star)>{{ $star }}</option>
                @endforeach
            </select>
            <select name="refundability" class="rounded border border-slate-300 px-3 py-2">
                <option value="">{{ __('public.results.refundability') }}</option>
                <option value="refundable" @selected(($filters['refundability'] ?? '') === 'refundable')>Refundable</option>
                <option value="non_refundable" @selected(($filters['refundability'] ?? '') === 'non_refundable')>Non-refundable</option>
            </select>
            <select name="board_basis" class="rounded border border-slate-300 px-3 py-2">
                <option value="">{{ __('public.results.board_basis') }}</option>
                <option value="bed_and_breakfast" @selected(($filters['board_basis'] ?? '') === 'bed_and_breakfast')>Bed and breakfast</option>
                <option value="half_board" @selected(($filters['board_basis'] ?? '') === 'half_board')>Half board</option>
            </select>
            <select name="sort" class="rounded border border-slate-300 px-3 py-2">
                <option value="recommended">{{ __('public.results.sort') }}</option>
                <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>Price low to high</option>
                <option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>Price high to low</option>
                <option value="star_rating" @selected(($filters['sort'] ?? '') === 'star_rating')>Star rating</option>
            </select>
            <button class="rounded bg-slate-900 px-4 py-2 text-white">{{ __('public.results.apply') }}</button>
        </form>

        <div class="mt-6 grid gap-4">
            @forelse ($results as $hotel)
                <article class="grid gap-4 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-[180px_1fr_auto]">
                    <div class="flex aspect-[4/3] items-center justify-center rounded bg-teal-50 text-sm font-medium text-teal-800">{{ __('public.brand') }}</div>
                    <div>
                        <h2 class="text-xl font-semibold text-slate-950">{{ $hotel['name'] }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ $hotel['location'] }} @if ($hotel['star_rating']) · {{ $hotel['star_rating'] }} stars @endif</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach (array_slice($hotel['facilities'], 0, 4) as $facility)
                                <span class="rounded bg-slate-100 px-2 py-1 text-xs text-slate-700">{{ str_replace('_', ' ', $facility) }}</span>
                            @endforeach
                        </div>
                        @if ($hotel['rates'])
                            <div class="mt-3 space-y-1">
                                @foreach (array_slice($hotel['rates'], 0, 2) as $rate)
                                    <p class="text-sm text-slate-700">{{ str_replace('_', ' ', $rate['board_basis']) }} · {{ str_replace('_', ' ', $rate['refundability']) }}</p>
                                    <p class="text-sm text-slate-600">{{ $rate['cancellation_summary'] }}</p>
                                    <p class="text-xs font-medium {{ $rate['requires_check_rate'] ? 'text-amber-700' : 'text-emerald-700' }}">
                                        {{ $rate['requires_check_rate'] ? __('public.booking.requires_recheck') : __('public.booking.bookable') }}
                                    </p>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="flex flex-col justify-between gap-4 md:items-end">
                        <div>
                            <p class="text-sm text-slate-500">{{ __('public.price.from') }}</p>
                            <p class="text-xl font-semibold text-slate-950">{{ $money->formatArray($hotel['minimum_price']) }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $hotel['taxes_known'] ? __('public.results.taxes_known') : __('public.results.taxes_unknown') }}</p>
                        </div>
                        <a class="rounded bg-teal-700 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-teal-800" href="{{ route('hotels.show', ['hotel' => $hotel['public_token'], 'search' => $searchSession->public_uuid, 'locale' => app()->getLocale()]) }}">
                            {{ __('public.results.view_hotel') }}
                        </a>
                    </div>
                </article>
            @empty
                <div class="rounded border border-slate-200 bg-white p-8 text-center text-slate-600">
                    {{ __('public.results.empty') }}
                </div>
            @endforelse
        </div>
    </section>
</x-layouts.public>
