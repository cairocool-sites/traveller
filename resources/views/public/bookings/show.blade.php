<x-layouts.public :meta-title="$metaTitle" :meta-description="$metaDescription">
    <section class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="rounded border border-slate-200 bg-white p-6">
            <p class="text-sm font-medium text-teal-700">{{ __('public.booking.confirmation_title') }}</p>
            <h1 class="mt-2 text-3xl font-semibold text-slate-950">{{ $booking->booking_reference }}</h1>
            <p class="mt-3 text-slate-600">{{ __('public.booking.status') }}: {{ $booking->status->value }}</p>
            <p class="mt-1 text-slate-600">{{ __('public.booking.total') }}: {{ $money->formatMinor($booking->total_amount_minor, $booking->currency->code) }}</p>
            @if ($booking->status === \App\Enums\BookingStatus::Confirmed && $booking->payment_status !== \App\Enums\PaymentStatus::Paid)
                <a href="{{ route('payments.show', ['booking' => $booking->public_uuid, 'locale' => app()->getLocale()]) }}" class="mt-4 inline-flex rounded bg-teal-700 px-5 py-2 text-sm font-semibold text-white">{{ __('public.payments.title') }}</a>
            @endif
            <p class="mt-4 text-sm text-slate-500">{{ __('public.booking.confirmation_note') }}</p>
        </div>
    </section>
</x-layouts.public>
