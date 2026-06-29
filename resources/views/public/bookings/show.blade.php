<x-layouts.public :meta-title="$metaTitle" :meta-description="$metaDescription">
    <section class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="cct-card p-6 sm:p-8">
            <p class="cct-badge bg-[#14B8A6]/15 text-[#0F766E]">{{ __('public.booking.confirmation_title') }}</p>
            <h1 class="mt-4 text-3xl font-black text-[#0B1F33]">{{ $booking->booking_reference }}</h1>
            <p class="mt-3 rounded-2xl bg-blue-50 p-4 text-sm font-semibold text-blue-900">{{ __('public.booking.sandbox_notice') }}</p>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-[#F6F8FB] p-4">
                    <p class="text-sm font-bold text-slate-500">{{ __('public.booking.status') }}</p>
                    <p class="mt-1 text-lg font-black text-[#0B1F33]">{{ $booking->status->value }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-[#F6F8FB] p-4">
                    <p class="text-sm font-bold text-slate-500">{{ __('public.booking.total') }}</p>
                    <p class="mt-1 text-lg font-black text-[#0B1F33]">{{ $money->formatMinor($booking->total_amount_minor, $booking->currency->code) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-[#F6F8FB] p-4">
                    <p class="text-sm font-bold text-slate-500">{{ __('public.booking.supplier_confirmation') }}</p>
                    <p class="mt-1 text-lg font-black text-[#0B1F33]">{{ $booking->supplier_confirmation_reference ?: __('public.booking.pending_review') }}</p>
                </div>
            </div>

            <div class="mt-6 rounded-2xl border border-slate-200 p-5">
                <h2 class="text-xl font-black text-[#0B1F33]">{{ $booking->hotel_snapshot['name'] ?? __('public.nav.hotels') }}</h2>
                <dl class="mt-4 grid gap-3 text-sm font-semibold text-slate-600 sm:grid-cols-2">
                    <div><dt class="text-slate-500">{{ __('public.booking.stay_dates') }}</dt><dd class="mt-1 text-[#0B1F33]">{{ $booking->check_in->toDateString() }} - {{ $booking->check_out->toDateString() }}</dd></div>
                    <div><dt class="text-slate-500">{{ __('public.details.rooms') }}</dt><dd class="mt-1 text-[#0B1F33]">{{ $booking->room_snapshot['room_name'] ?? '-' }}</dd></div>
                    <div><dt class="text-slate-500">{{ __('public.details.board') }}</dt><dd class="mt-1 text-[#0B1F33]">{{ $booking->room_snapshot['board_basis'] ?? '-' }}</dd></div>
                    <div><dt class="text-slate-500">{{ __('public.booking.occupancy') }}</dt><dd class="mt-1 text-[#0B1F33]">{{ $booking->adults_count }} {{ __('public.search.adults') }} / {{ $booking->children_count }} {{ __('public.search.children') }}</dd></div>
                </dl>
            </div>

            <div class="mt-6 rounded-2xl border border-slate-200 p-5">
                <h2 class="text-xl font-black text-[#0B1F33]">{{ __('public.booking.guests') }}</h2>
                <ul class="mt-4 space-y-2 text-sm font-semibold text-slate-600">
                    @foreach ($booking->guests as $guest)
                        <li>{{ $guest->first_name }} {{ $guest->last_name }} @if ($guest->is_lead_guest) <span class="text-[#0F766E]">({{ __('public.booking.lead_guest') }})</span> @endif</li>
                    @endforeach
                </ul>
            </div>

            @if ($booking->status === \App\Enums\BookingStatus::Confirmed && $booking->payment_status !== \App\Enums\PaymentStatus::Paid)
                <a href="{{ route('payments.show', ['booking' => $booking->public_uuid, 'locale' => app()->getLocale()]) }}" class="mt-4 inline-flex rounded bg-teal-700 px-5 py-2 text-sm font-semibold text-white">{{ __('public.payments.title') }}</a>
            @endif
            @if ($booking->status === \App\Enums\BookingStatus::Confirmed)
                <a href="{{ route('cancellations.create', ['booking' => $booking->public_uuid, 'locale' => app()->getLocale()]) }}" class="mt-4 inline-flex rounded border border-slate-300 px-5 py-2 text-sm font-semibold text-slate-800">{{ __('public.cancellations.title') }}</a>
            @endif
            <p class="mt-6 text-sm font-semibold text-slate-500">{{ __('public.booking.confirmation_note') }}</p>
        </div>
    </section>
</x-layouts.public>
