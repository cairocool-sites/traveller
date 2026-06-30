<form wire:submit="submit" class="cct-card p-4">
    @if ($errors->any())
        <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800" role="alert">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-3 lg:grid-cols-12">
        <div class="relative lg:col-span-4">
            <label for="destination" class="cct-label">{{ __('public.search.destination') }}</label>
            <div class="relative">
                <svg class="pointer-events-none absolute start-4 top-1/2 size-5 -translate-y-1/2 text-[#0F766E]" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 21s7-4.8 7-11a7 7 0 1 0-14 0c0 6.2 7 11 7 11Z" stroke="currentColor" stroke-width="2" />
                    <path d="M12 10.5h.01" stroke="currentColor" stroke-width="4" stroke-linecap="round" />
                </svg>
                <input id="destination" type="text" wire:model.live.debounce.350ms="destinationTerm" autocomplete="off" class="cct-input min-h-11 py-2.5 ps-12" placeholder="{{ __('public.search.destination_placeholder') }}">
            </div>
            <input type="hidden" wire:model="destination">
            @if ($destinationOptions)
                <div class="absolute z-30 mt-2 max-h-72 w-full overflow-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl">
                    @foreach ($destinationOptions as $option)
                        <button type="button" wire:click="selectDestination('{{ $option['token'] }}', '{{ addslashes($option['label']) }}')" class="flex w-full items-center justify-between gap-3 rounded-xl px-4 py-3 text-start text-sm transition hover:bg-[#14B8A6]/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0F766E]">
                            <span class="min-w-0">
                                <span class="block truncate font-extrabold text-[#0B1F33]">{{ $option['label'] }}</span>
                                <span class="mt-1 block text-xs font-bold text-slate-500">{{ __('public.search.option_help.'.$option['type']) }}</span>
                            </span>
                            <span class="shrink-0 rounded-full border border-[#0F766E]/15 bg-[#0F766E]/10 px-3 py-1 text-xs font-black text-[#0F766E]">
                                {{ __('public.search.option_types.'.$option['type']) }}
                            </span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="lg:col-span-2">
            <label for="checkIn" class="cct-label">{{ __('public.search.check_in') }}</label>
            <input id="checkIn" type="date" wire:model="checkIn" min="{{ now()->toDateString() }}" class="cct-input min-h-11 py-2.5">
        </div>

        <div class="lg:col-span-2">
            <label for="checkOut" class="cct-label">{{ __('public.search.check_out') }}</label>
            <input id="checkOut" type="date" wire:model="checkOut" min="{{ now()->addDay()->toDateString() }}" class="cct-input min-h-11 py-2.5">
        </div>

        <div class="lg:col-span-2">
            <label for="rooms" class="cct-label">{{ __('public.search.rooms') }}</label>
            <select id="rooms" wire:model="rooms" class="cct-input min-h-11 py-2.5">
                @for ($room = 1; $room <= $maxRooms; $room++)
                    <option value="{{ $room }}">{{ $room }}</option>
                @endfor
            </select>
        </div>

        <div class="lg:col-span-2">
            <label for="currency" class="cct-label">{{ __('public.search.currency') }}</label>
            <input type="hidden" wire:model="currency">
            <div id="currency" class="cct-input flex min-h-11 items-center py-2.5 font-black text-[#0B1F33]">
                {{ config('travel.currency.default') }}
            </div>
        </div>
    </div>

    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-12">
        <div class="lg:col-span-3">
            <label for="adults" class="cct-label">{{ __('public.search.adults') }}</label>
            <select id="adults" wire:model="adults" class="cct-input min-h-11 py-2.5">
                @for ($adult = 1; $adult <= $maxAdults; $adult++)
                    <option value="{{ $adult }}">{{ $adult }}</option>
                @endfor
            </select>
        </div>

        <div class="lg:col-span-3">
            <label for="children" class="cct-label">{{ __('public.search.children') }}</label>
            <select id="children" wire:model.live="children" class="cct-input min-h-11 py-2.5">
                @for ($child = 0; $child <= $maxChildren; $child++)
                    <option value="{{ $child }}">{{ $child }}</option>
                @endfor
            </select>
        </div>

        @foreach ($childAges as $index => $age)
            <div class="lg:col-span-2">
                <label for="childAge{{ $index }}" class="cct-label">{{ __('public.search.child_age', ['number' => $index + 1]) }}</label>
                <input id="childAge{{ $index }}" type="number" wire:model="childAges.{{ $index }}" min="0" max="{{ $maxChildAge }}" class="cct-input min-h-11 py-2.5">
            </div>
        @endforeach

        <div class="sm:col-span-2 lg:col-span-3 lg:col-start-auto">
            <label class="cct-label invisible hidden lg:block" aria-hidden="true">{{ __('public.search.submit') }}</label>
            <button type="submit" class="cct-button min-h-11 w-full py-2.5">
                <svg class="me-2 size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="m21 21-4.3-4.3M10.8 18a7.2 7.2 0 1 1 0-14.4 7.2 7.2 0 0 1 0 14.4Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
                {{ __('public.search.submit') }}
            </button>
        </div>
    </div>
</form>
