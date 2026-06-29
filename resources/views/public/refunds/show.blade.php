<x-layouts.public :meta-title="$metaTitle" :meta-description="$metaDescription">
    <meta name="robots" content="noindex">
    <section class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="rounded border border-slate-200 bg-white p-6">
            <h1 class="text-2xl font-semibold">{{ __('public.refunds.title') }}</h1>
            <p class="mt-2">{{ __('public.payments.booking_reference') }}: {{ $refund->booking->booking_reference }}</p>
            <p>{{ __('public.booking.status') }}: {{ $refund->status->value }}</p>
            <p>{{ __('public.cancellations.refundable') }}: {{ $money->formatMinor($refund->requested_amount_minor, $refund->currency->code) }}</p>
            <p class="mt-3 text-sm text-slate-600">{{ config('travel.refunds.customer_processing_message') }}</p>
        </div>
    </section>
</x-layouts.public>
