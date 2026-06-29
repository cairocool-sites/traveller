<form wire:submit="submit" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
    @if ($errors->any())
        <div class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-12">
        <div class="relative lg:col-span-4">
            <label for="destination" class="block text-sm font-medium text-slate-800">{{ __('public.search.destination') }}</label>
            <input id="destination" type="text" wire:model.live.debounce.350ms="destinationTerm" autocomplete="off" class="mt-1 w-full rounded border border-slate-300 px-3 py-2" placeholder="{{ __('public.search.destination_placeholder') }}">
            <input type="hidden" wire:model="destination">
            @if ($destinationOptions)
                <div class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded border border-slate-200 bg-white shadow-lg">
                    @foreach ($destinationOptions as $option)
                        <button type="button" wire:click="selectDestination('{{ $option['token'] }}', '{{ addslashes($option['label']) }}')" class="block w-full px-3 py-2 text-start text-sm hover:bg-teal-50">
                            <span class="font-medium">{{ $option['label'] }}</span>
                            <span class="text-xs uppercase text-slate-500">{{ $option['type'] }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="lg:col-span-2">
            <label for="checkIn" class="block text-sm font-medium text-slate-800">{{ __('public.search.check_in') }}</label>
            <input id="checkIn" type="date" wire:model="checkIn" min="{{ now()->toDateString() }}" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
        </div>
        <div class="lg:col-span-2">
            <label for="checkOut" class="block text-sm font-medium text-slate-800">{{ __('public.search.check_out') }}</label>
            <input id="checkOut" type="date" wire:model="checkOut" min="{{ now()->addDay()->toDateString() }}" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
        </div>
        <div class="lg:col-span-2">
            <label for="rooms" class="block text-sm font-medium text-slate-800">{{ __('public.search.rooms') }}</label>
            <input id="rooms" type="number" wire:model="rooms" min="1" max="{{ $maxRooms }}" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
        </div>
        <div class="lg:col-span-2">
            <label for="currency" class="block text-sm font-medium text-slate-800">{{ __('public.search.currency') }}</label>
            <select id="currency" wire:model="currency" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                @foreach ($currencies as $currencyOption)
                    <option value="{{ $currencyOption->code }}">{{ $currencyOption->code }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div>
            <label for="adults" class="block text-sm font-medium text-slate-800">{{ __('public.search.adults') }}</label>
            <input id="adults" type="number" wire:model="adults" min="1" max="{{ $maxAdults }}" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
        </div>
        <div>
            <label for="children" class="block text-sm font-medium text-slate-800">{{ __('public.search.children') }}</label>
            <input id="children" type="number" wire:model.live="children" min="0" max="{{ $maxChildren }}" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
        </div>
        @foreach ($childAges as $index => $age)
            <div>
                <label for="childAge{{ $index }}" class="block text-sm font-medium text-slate-800">{{ __('public.search.child_age', ['number' => $index + 1]) }}</label>
                <input id="childAge{{ $index }}" type="number" wire:model="childAges.{{ $index }}" min="0" max="{{ $maxChildAge }}" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            </div>
        @endforeach
        <div>
            <label for="locale" class="block text-sm font-medium text-slate-800">{{ __('public.search.locale') }}</label>
            <select id="locale" wire:model="locale" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                <option value="ar">Arabic</option>
                <option value="en">English</option>
            </select>
        </div>
    </div>

    <div class="mt-5 flex justify-end">
        <button type="submit" class="rounded bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">
            {{ __('public.search.submit') }}
        </button>
    </div>
</form>
