<?php

namespace App\Filament\Pages;

use App\Support\Admin\Access;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

class RolesAndPermissions extends Page
{
    protected string $view = 'filament.pages.roles-and-permissions';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return Gate::allows('view_roles');
    }

    public function getTitle(): string
    {
        return __('admin.roles.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.roles.navigation_label');
    }

    /**
     * @return array<int, string>
     */
    public function roles(): array
    {
        return Access::ROLES;
    }

    /**
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return Access::PERMISSIONS;
    }

    public function roleHasPermission(string $role, string $permission): bool
    {
        return in_array($permission, Access::ROLE_PERMISSIONS[$role] ?? [], true);
    }
}
