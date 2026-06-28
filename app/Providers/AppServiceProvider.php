<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);

        Gate::define('view_roles', fn (User $user): bool => $user->hasPermissionTo('view_roles'));
        Gate::define('manage_roles', fn (User $user): bool => $user->hasPermissionTo('manage_roles'));
        Gate::define('view_audit_logs', fn (User $user): bool => $user->hasPermissionTo('view_audit_logs'));
        Gate::define('manage_system_settings', fn (User $user): bool => $user->hasPermissionTo('manage_system_settings'));
    }
}
