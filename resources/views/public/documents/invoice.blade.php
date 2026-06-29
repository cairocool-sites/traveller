<x-layouts.public :meta-title="$document->invoice_number" :meta-description="$document->invoice_number">
    <meta name="robots" content="noindex">
    <section class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <article class="rounded border border-slate-200 bg-white p-6 print:border-0">
            <h1 class="text-3xl font-semibold">{{ $document->invoice_number }}</h1>
            <p class="mt-2">{{ $document->snapshot['label'] }}</p>
            <p>{{ __('public.payments.booking_reference') }}: {{ $document->snapshot['booking_reference'] }}</p>
            <p>{{ __('public.booking.total') }}: {{ $document->total_minor }} {{ $document->currency->code }}</p>
        </article>
    </section>
</x-layouts.public>
