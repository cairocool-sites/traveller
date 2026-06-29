<x-layouts.public :meta-title="__('public.documents.verify')" :meta-description="__('public.documents.verify')">
    <meta name="robots" content="noindex">
    <section class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="rounded border border-emerald-200 bg-emerald-50 p-6">
            <h1 class="text-2xl font-semibold text-emerald-950">{{ __('public.documents.verified') }}</h1>
            <p class="mt-2 text-emerald-900">{{ __('public.documents.type') }}: {{ $type }}</p>
            <p class="text-emerald-900">{{ __('public.documents.issued_at') }}: {{ $document->issued_at->toDateTimeString() }}</p>
        </div>
    </section>
</x-layouts.public>
