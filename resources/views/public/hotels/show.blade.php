@php
    $translation = $canonicalHotel?->translation(app()->getLocale());
    $hotelName = $translation?->translated_name ?? $canonicalHotel?->name ?? $result['name'];
    $rates = $result['rates'] ?? ($supplierDetails?->hotel->rooms ? collect($supplierDetails->hotel->rooms)->map(fn ($rate) => [
        'room_name' => $rate->roomName,
        'board_basis' => $rate->boardBasis->value,
        'total' => $rate->totalAmount->jsonSerialize(),
        'refundability' => $rate->refundability->value,
        'cancellation_summary' => app(\App\Services\PublicSearch\CancellationSummaryService::class)->summarize($rate->cancellationPolicies, app()->getLocale()),
        'occupancy' => $rate->occupancy->jsonSerialize(),
    ])->all() : []);
@endphp
<x-layouts.public :meta-title="$metaTitle" :meta-description="$metaDescription">
    <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <nav class="text-sm text-slate-500" aria-label="Breadcrumb">
            <a href="{{ route('home', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.home') }}</a>
            <span>/</span>
            <a href="{{ route('hotels.index', ['locale' => app()->getLocale()]) }}">{{ __('public.nav.hotels') }}</a>
            <span>/</span>
            <span>{{ $hotelName }}</span>
        </nav>

        @foreach ($warnings as $warning)
            <div class="mt-4 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">{{ $warning }}</div>
        @endforeach

        @unless ($canonicalHotel)
            <div class="mt-4 rounded border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">{{ __('public.details.unmapped_notice') }}</div>
        @endunless

        <div class="mt-6 grid gap-8 lg:grid-cols-[1fr_360px]">
            <div>
                <div class="flex aspect-[16/7] items-center justify-center rounded-lg bg-teal-50 text-lg font-semibold text-teal-800">{{ __('public.brand') }}</div>
                <h1 class="mt-6 text-3xl font-semibold text-slate-950">{{ $hotelName }}</h1>
                <p class="mt-2 text-slate-600">
                    {{ $canonicalHotel?->city?->name_en ?? $result['location'] }}
                    @if ($canonicalHotel?->area) · {{ $canonicalHotel->area->name_en }} @endif
                    @if ($canonicalHotel?->star_rating ?? $result['star_rating'] ?? null) · {{ $canonicalHotel?->star_rating ?? $result['star_rating'] }} stars @endif
                </p>
                <p class="mt-4 leading-7 text-slate-700">{{ $translation?->description ?? $translation?->short_description ?? $supplierDetails?->hotel->name ?? $result['name'] }}</p>

                <section class="mt-8">
                    <h2 class="text-xl font-semibold text-slate-950">{{ __('public.details.rooms') }}</h2>
                    <div class="mt-4 grid gap-3">
                        @foreach ($rates as $rate)
                            <div class="rounded border border-slate-200 bg-white p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h3 class="font-semibold text-slate-950">{{ $rate['room_name'] }}</h3>
                                        <p class="mt-1 text-sm text-slate-600">{{ str_replace('_', ' ', $rate['board_basis']) }} · {{ str_replace('_', ' ', $rate['refundability']) }}</p>
                                        <p class="mt-1 text-sm text-slate-600">{{ $rate['cancellation_summary'] }}</p>
                                        <p class="mt-1 text-sm text-slate-500">{{ $rate['occupancy']['adults'] }} adults · {{ $rate['occupancy']['children'] }} children</p>
                                    </div>
                                    <div class="sm:text-end">
                                        <p class="text-lg font-semibold text-slate-950">{{ $money->formatArray($rate['total']) }}</p>
                                        <button type="button" disabled class="mt-2 rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-600">{{ __('public.details.booking_disabled') }}</button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>

            <aside class="space-y-4">
                <div class="rounded border border-slate-200 bg-white p-5">
                    <h2 class="font-semibold text-slate-950">{{ __('admin.facilities.plural_model_label') }}</h2>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @forelse ($canonicalHotel?->facilities ?? collect($result['facilities'] ?? []) as $facility)
                            <span class="rounded bg-slate-100 px-2 py-1 text-xs text-slate-700">
                                {{ is_string($facility) ? str_replace('_', ' ', $facility) : ($facility->translation(app()->getLocale())?->name ?? $facility->code) }}
                            </span>
                        @empty
                            <span class="text-sm text-slate-500">{{ __('public.cancellation.unknown') }}</span>
                        @endforelse
                    </div>
                </div>
                <div class="rounded border border-slate-200 bg-white p-5">
                    <h2 class="font-semibold text-slate-950">{{ __('public.details.policies') }}</h2>
                    <p class="mt-2 text-sm text-slate-600">{{ $canonicalHotel?->policy?->important_information ?? $canonicalHotel?->policy?->cancellation_notes ?? __('public.cancellation.unknown') }}</p>
                </div>
                <div class="rounded border border-slate-200 bg-white p-5">
                    <h2 class="font-semibold text-slate-950">{{ __('public.details.map_placeholder') }}</h2>
                    <p class="mt-2 text-sm text-slate-600">{{ $canonicalHotel?->address_line_1 ?? $result['location'] }}</p>
                </div>
            </aside>
        </div>
    </section>
</x-layouts.public>
