<x-layouts.public :meta-title="$metaTitle" :meta-description="$metaDescription">
    <meta name="robots" content="noindex">
    <section class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-semibold text-slate-950">{{ __('public.cancellations.title') }}</h1>
        <div class="mt-4 rounded border border-slate-200 bg-white p-5">
            <p>{{ __('public.payments.booking_reference') }}: {{ $booking->booking_reference }}</p>
            <p>{{ $booking->hotel_snapshot['name'] ?? 'Hotel' }}</p>
            <p>{{ $booking->check_in->toDateString() }} - {{ $booking->check_out->toDateString() }}</p>
            <p>{{ __('public.cancellations.penalty') }}: {{ $money->formatMinor($eligibility->penaltyMinor, $booking->currency->code) }}</p>
            <p>{{ __('public.cancellations.refundable') }}: {{ $money->formatMinor($eligibility->refundableMinor, $booking->currency->code) }}</p>
            <p class="mt-2 text-sm text-slate-600">{{ $eligibility->reason }}</p>
        </div>
        @error('cancellation') <div class="mt-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-900">{{ $message }}</div> @enderror
        <form method="POST" action="{{ route('cancellations.store', ['booking' => $booking->public_uuid, 'locale' => app()->getLocale()]) }}" class="mt-6 space-y-4 rounded border border-slate-200 bg-white p-5">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <label class="block text-sm font-medium text-slate-700">{{ __('public.cancellations.reason') }}<textarea name="customer_reason" rows="3" class="mt-1 w-full rounded border-slate-300"></textarea></label>
            @if ($eligibility->nonRefundable)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="acknowledge_non_refundable" value="1"> {{ __('public.cancellations.acknowledge_non_refundable') }}</label>
            @endif
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="confirm" value="1"> {{ __('public.cancellations.confirm') }}</label>
            <button class="rounded bg-red-700 px-5 py-2 text-sm font-semibold text-white">{{ __('public.cancellations.submit') }}</button>
        </form>
    </section>
</x-layouts.public>
