<x-layouts.public :meta-title="$document->voucher_number" :meta-description="$document->voucher_number">
    <meta name="robots" content="noindex">
    <section class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <article class="rounded border border-slate-200 bg-white p-6 print:border-0">
            <h1 class="text-3xl font-semibold">{{ $document->snapshot['company_name'] }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ $document->voucher_number }}</p>
            <p class="mt-4">{{ __('public.payments.booking_reference') }}: {{ $document->snapshot['booking_reference'] }}</p>
            <p>{{ $document->snapshot['hotel_name'] }}</p>
            <p>{{ $document->snapshot['check_in'] }} - {{ $document->snapshot['check_out'] }}</p>
            <p class="mt-4 text-sm text-slate-600">{{ $document->snapshot['important_notes'] }}</p>
        </article>
    </section>
</x-layouts.public>
