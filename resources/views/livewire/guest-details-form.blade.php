<form wire:submit="submit" class="mt-6 space-y-5 rounded border border-slate-200 bg-white p-5">
    @error('rate') <div class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-900">{{ $message }}</div> @enderror
    @error('guests') <div class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-900">{{ $message }}</div> @enderror

    <div class="grid gap-4 sm:grid-cols-2">
        <label class="text-sm font-medium text-slate-700">
            {{ __('public.booking.contact_email') }}
            <input type="email" wire:model="contact_email" class="mt-1 w-full rounded border-slate-300" autocomplete="email">
            @error('contact_email') <span class="text-xs text-red-700">{{ $message }}</span> @enderror
        </label>
        <label class="text-sm font-medium text-slate-700">
            {{ __('public.booking.contact_phone') }}
            <input type="text" wire:model="contact_phone" class="mt-1 w-full rounded border-slate-300" autocomplete="tel">
        </label>
    </div>

    <div class="space-y-4">
        <h2 class="text-lg font-semibold text-slate-950">{{ __('public.booking.guests') }}</h2>
        @foreach ($guests as $index => $guest)
            <div class="grid gap-3 rounded border border-slate-200 p-4 sm:grid-cols-[1fr_1fr_auto]">
                <input type="hidden" wire:model="guests.{{ $index }}.type">
                <input type="hidden" wire:model="guests.{{ $index }}.age">
                <label class="text-sm font-medium text-slate-700">
                    {{ __('public.booking.first_name') }}
                    <input type="text" wire:model="guests.{{ $index }}.first_name" class="mt-1 w-full rounded border-slate-300">
                </label>
                <label class="text-sm font-medium text-slate-700">
                    {{ __('public.booking.last_name') }}
                    <input type="text" wire:model="guests.{{ $index }}.last_name" class="mt-1 w-full rounded border-slate-300">
                </label>
                <label class="flex items-center gap-2 pt-6 text-sm text-slate-700">
                    <input type="checkbox" wire:model="guests.{{ $index }}.is_lead_guest" @disabled($guest['type'] !== 'adult')>
                    {{ __('public.booking.lead_guest') }}
                </label>
            </div>
        @endforeach
    </div>

    <label class="block text-sm font-medium text-slate-700">
        {{ __('public.booking.special_requests') }}
        <textarea wire:model="special_requests" rows="3" class="mt-1 w-full rounded border-slate-300"></textarea>
    </label>

    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" wire:model="accept_price_change">
        {{ __('public.booking.accept_price_change') }}
    </label>

    <button type="submit" wire:loading.attr="disabled" class="rounded bg-teal-700 px-5 py-2 text-sm font-semibold text-white disabled:opacity-60">
        {{ __('public.booking.submit') }}
    </button>
</form>
