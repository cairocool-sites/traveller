<x-layouts.public :meta-title="$metaTitle" :meta-description="$metaDescription">
    <section class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-semibold text-slate-950">{{ __('public.payments.title') }}</h1>
        <div class="mt-4 rounded border border-slate-200 bg-white p-5">
            <p>{{ __('public.booking.status') }}: {{ $booking->status->value }}</p>
            <p>{{ __('public.payments.booking_reference') }}: {{ $booking->booking_reference }}</p>
            <p>{{ __('public.booking.total') }}: {{ $money->formatMinor($booking->total_amount_minor, $booking->currency->code) }}</p>
        </div>

        @if (session('status'))
            <div class="mt-4 rounded border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</div>
        @endif
        @error('payment')
            <div class="mt-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-900">{{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('payments.store', ['booking' => $booking->public_uuid, 'locale' => app()->getLocale()]) }}" enctype="multipart/form-data" class="mt-6 space-y-4 rounded border border-slate-200 bg-white p-5">
            @csrf
            <label class="block text-sm font-medium text-slate-700">
                {{ __('public.payments.method') }}
                <select name="manual_payment_method_id" class="mt-1 w-full rounded border-slate-300">
                    @foreach ($methods as $method)
                        <option value="{{ $method->id }}">{{ $method->localizedName() }} - {{ $method->localizedInstructions() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-medium text-slate-700">
                {{ __('public.payments.reference') }}
                <input name="submitted_reference" value="{{ old('submitted_reference') }}" class="mt-1 w-full rounded border-slate-300">
            </label>
            <label class="block text-sm font-medium text-slate-700">
                {{ __('public.payments.evidence') }}
                <input type="file" name="evidence" class="mt-1 w-full rounded border-slate-300">
            </label>
            <label class="block text-sm font-medium text-slate-700">
                {{ __('public.payments.notes') }}
                <textarea name="customer_notes" rows="3" class="mt-1 w-full rounded border-slate-300">{{ old('customer_notes') }}</textarea>
            </label>
            <button class="rounded bg-teal-700 px-5 py-2 text-sm font-semibold text-white">{{ __('public.payments.submit') }}</button>
        </form>
    </section>
</x-layouts.public>
