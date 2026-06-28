<x-filament-panels::page>
    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700 dark:text-gray-200">
                        {{ __('admin.roles.role') }}
                    </th>

                    @foreach ($this->permissions() as $permission)
                        <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-200">
                            {{ __('admin.permissions.' . $permission) }}
                        </th>
                    @endforeach
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($this->roles() as $role)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">
                            {{ __('admin.roles.names.' . $role) }}
                        </td>

                        @foreach ($this->permissions() as $permission)
                            <td class="px-4 py-3 text-center">
                                @if ($this->roleHasPermission($role, $permission))
                                    <span class="text-success-600 dark:text-success-400">{{ __('admin.common.yes') }}</span>
                                @else
                                    <span class="text-gray-400">{{ __('admin.common.no') }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
