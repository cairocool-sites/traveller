<x-layouts.public :meta-title="$document->receipt_number" :meta-description="$document->receipt_number">
    <meta name="robots" content="noindex">
    <section class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <article class="rounded border border-slate-200 bg-white p-6 print:border-0">
            <h1 class="text-3xl font-semibold">{{ $document->receipt_number }}</h1>
            <p>{{ __('public.payments.booking_reference') }}: {{ $document->snapshot['booking_reference'] }}</p>
            <p>{{ __('public.booking.total') }}: {{ $document->amount_minor }} {{ $document->currency->code }}</p>
        </article>
    </section>
</x-layouts.public>
