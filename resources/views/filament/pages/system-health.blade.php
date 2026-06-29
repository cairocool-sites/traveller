<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($this->summary() as $key => $value)
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('admin.system_health.metrics.' . $key) }}
                </x-slot>

                @if (is_array($value) && array_key_exists('ok', $value))
                    <div class="text-sm font-medium {{ $value['ok'] ? 'text-success-600' : 'text-danger-600' }}">
                        {{ $value['ok'] ? __('admin.system_health.ok') : __('admin.system_health.needs_attention') }}
                    </div>
                    <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        {{ $value['message'] ?? __('admin.common.not_available') }}
                    </div>
                @elseif (is_array($value))
                    <div class="space-y-2">
                        @foreach ($value as $check => $checkValue)
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span>{{ __('admin.system_health.metrics.' . $check) }}</span>
                                <span class="{{ ($checkValue['ok'] ?? false) ? 'text-success-600' : 'text-danger-600' }}">
                                    {{ ($checkValue['ok'] ?? false) ? __('admin.system_health.ok') : __('admin.system_health.needs_attention') }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-2xl font-semibold">{{ $value }}</div>
                @endif
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
