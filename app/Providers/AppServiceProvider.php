<?php

namespace App\Providers;

use App\Models\Area;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Facility;
use App\Models\User;
use App\Policies\AreaPolicy;
use App\Policies\CityPolicy;
use App\Policies\CountryPolicy;
use App\Policies\CurrencyPolicy;
use App\Policies\ExchangeRatePolicy;
use App\Policies\FacilityPolicy;
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
        Gate::policy(Country::class, CountryPolicy::class);
        Gate::policy(City::class, CityPolicy::class);
        Gate::policy(Area::class, AreaPolicy::class);
        Gate::policy(Currency::class, CurrencyPolicy::class);
        Gate::policy(ExchangeRate::class, ExchangeRatePolicy::class);
        Gate::policy(Facility::class, FacilityPolicy::class);

        Gate::define('view_roles', fn (User $user): bool => $user->hasPermissionTo('view_roles'));
        Gate::define('manage_roles', fn (User $user): bool => $user->hasPermissionTo('manage_roles'));
        Gate::define('view_audit_logs', fn (User $user): bool => $user->hasPermissionTo('view_audit_logs'));
        Gate::define('manage_system_settings', fn (User $user): bool => $user->hasPermissionTo('manage_system_settings'));
    }
}
