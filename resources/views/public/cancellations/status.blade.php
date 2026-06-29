<x-layouts.public :meta-title="$metaTitle" :meta-description="$metaDescription">
    <meta name="robots" content="noindex">
    <section class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="rounded border border-slate-200 bg-white p-6">
            <h1 class="text-2xl font-semibold">{{ __('public.cancellations.status_title') }}</h1>
            <p class="mt-2">{{ __('public.payments.booking_reference') }}: {{ $cancellation->booking->booking_reference }}</p>
            <p>{{ __('public.booking.status') }}: {{ $cancellation->status->value }}</p>
            <p>{{ __('public.cancellations.refundable') }}: {{ $money->formatMinor($cancellation->refundable_amount_minor, $cancellation->currency->code) }}</p>
        </div>
    </section>
</x-layouts.public>
