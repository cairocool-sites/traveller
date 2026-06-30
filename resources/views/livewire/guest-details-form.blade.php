<form wire:submit="submit" class="mt-7 space-y-6 rounded-2xl border border-slate-200 bg-[#F6F8FB] p-5">
    @error('rate') <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-900">{{ $message }}</div> @enderror
    @error('guests') <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-900">{{ $message }}</div> @enderror

    <div class="grid gap-4 sm:grid-cols-2">
        <label>
            <span class="cct-label">{{ __('public.booking.contact_email') }}</span>
            <input type="email" wire:model="contact_email" class="cct-input" autocomplete="email">
            @error('contact_email') <span class="mt-1 block text-xs font-bold text-red-700">{{ $message }}</span> @enderror
        </label>
        <label>
            <span class="cct-label">{{ __('public.booking.contact_phone') }}</span>
            <input type="text" wire:model="contact_phone" class="cct-input" autocomplete="tel">
            @error('contact_phone') <span class="mt-1 block text-xs font-bold text-red-700">{{ $message }}</span> @enderror
        </label>
        <label>
            <span class="cct-label">{{ __('public.booking.nationality') }}</span>
            <select wire:model="customer_nationality" class="cct-input">
                @foreach ($countries as $country)
                    <option value="{{ $country->iso2 }}">{{ app()->getLocale() === 'ar' ? $country->nationality_ar : $country->nationality_en }}</option>
                @endforeach
            </select>
            @error('customer_nationality') <span class="mt-1 block text-xs font-bold text-red-700">{{ $message }}</span> @enderror
        </label>
    </div>

    <div class="space-y-4">
        <h2 class="text-xl font-black text-[#0B1F33]">{{ __('public.booking.guests') }}</h2>
        @foreach ($guests as $index => $guest)
            <div class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-[1fr_1fr_auto]">
                <input type="hidden" wire:model="guests.{{ $index }}.type">
                <input type="hidden" wire:model="guests.{{ $index }}.age">
                <label>
                    <span class="cct-label">{{ __('public.booking.first_name') }}</span>
                    <input type="text" wire:model="guests.{{ $index }}.first_name" class="cct-input">
                    @error("guests.{$index}.first_name") <span class="mt-1 block text-xs font-bold text-red-700">{{ $message }}</span> @enderror
                </label>
                <label>
                    <span class="cct-label">{{ __('public.booking.last_name') }}</span>
                    <input type="text" wire:model="guests.{{ $index }}.last_name" class="cct-input">
                    @error("guests.{$index}.last_name") <span class="mt-1 block text-xs font-bold text-red-700">{{ $message }}</span> @enderror
                </label>
                <label class="flex min-h-12 items-center gap-2 pt-6 text-sm font-bold text-slate-700">
                    <input type="checkbox" wire:model="guests.{{ $index }}.is_lead_guest" class="size-5 rounded border-slate-300 text-[#0F766E]" @disabled($guest['type'] !== 'adult')>
                    {{ __('public.booking.lead_guest') }}
                </label>
            </div>
        @endforeach
    </div>

    <label class="block">
        <span class="cct-label">{{ __('public.booking.special_requests') }}</span>
        <textarea wire:model="special_requests" rows="3" class="cct-input"></textarea>
    </label>

    <div class="rounded-2xl border border-slate-200 bg-white p-4">
        <h2 class="text-lg font-black text-[#0B1F33]">{{ __('public.booking.final_review_title') }}</h2>
        <dl class="mt-4 grid gap-3 text-sm font-semibold text-slate-600 sm:grid-cols-2">
            <div>
                <dt class="text-slate-500">{{ __('public.booking.confirmed_price') }}</dt>
                <dd class="mt-1 text-lg font-black text-[#0B1F33]">{{ $rateCheck->currency->code }} {{ number_format(($rateCheck->checked_amount_minor ?? $rateCheck->original_amount_minor) / 100, 2) }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">{{ __('public.details.rooms') }}</dt>
                <dd class="mt-1 text-[#0B1F33]">{{ $rateCheck->room_snapshot['room_name'] }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">{{ __('public.booking.stay_dates') }}</dt>
                <dd class="mt-1 text-[#0B1F33]">{{ $rateCheck->searchSession->check_in->toDateString() }} - {{ $rateCheck->searchSession->check_out->toDateString() }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">{{ __('public.booking.occupancy') }}</dt>
                <dd class="mt-1 text-[#0B1F33]">{{ collect($rateCheck->occupancy_snapshot)->sum('adults') }} {{ __('public.search.adults') }} / {{ collect($rateCheck->occupancy_snapshot)->sum('children') }} {{ __('public.search.children') }}</dd>
            </div>
        </dl>
        <p class="mt-4 rounded-xl bg-blue-50 p-3 text-sm font-semibold text-blue-900">
            {{ config('travel.booking.submission_mode') === 'manual_review' ? __('public.booking.manual_review_submission_notice') : __('public.booking.sandbox_notice') }}
        </p>
    </div>

    @if ($rateCheck->price_changed)
        <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm font-semibold text-slate-700">
            <input type="checkbox" wire:model="accept_price_change" class="mt-0.5 size-5 rounded border-slate-300 text-[#0F766E]">
            <span>{{ __('public.booking.accept_price_change') }}</span>
        </label>
    @endif

    <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm font-semibold text-slate-700">
        <input type="checkbox" wire:model="confirmation_accepted" class="mt-0.5 size-5 rounded border-slate-300 text-[#0F766E]">
        <span>{{ __('public.booking.confirm_booking_checkbox') }}</span>
    </label>
    @error('confirmation_accepted') <span class="block text-xs font-bold text-red-700">{{ $message }}</span> @enderror

    <button type="submit" wire:loading.attr="disabled" class="cct-button w-full sm:w-auto">
        {{ __('public.booking.submit') }}
    </button>
</form>
