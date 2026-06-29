<?php

namespace App\Providers;

use App\Models\Area;
use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\BookingStatusHistory;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Facility;
use App\Models\Hotel;
use App\Models\HotelContact;
use App\Models\HotelImage;
use App\Models\HotelPolicy as HotelPolicyModel;
use App\Models\Invoice;
use App\Models\ManualPaymentMethod;
use App\Models\Payment;
use App\Models\RateCheck;
use App\Models\Receipt;
use App\Models\Refund;
use App\Models\SearchSession;
use App\Models\Supplier;
use App\Models\SupplierCredential;
use App\Models\SupplierOperationLog;
use App\Models\User;
use App\Models\Voucher;
use App\Policies\AreaPolicy;
use App\Policies\BookingCancellationPolicy;
use App\Policies\BookingPolicy;
use App\Policies\BookingStatusHistoryPolicy;
use App\Policies\CityPolicy;
use App\Policies\CountryPolicy;
use App\Policies\CurrencyPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\ExchangeRatePolicy;
use App\Policies\FacilityPolicy;
use App\Policies\HotelContactPolicy;
use App\Policies\HotelImagePolicy;
use App\Policies\HotelPolicy;
use App\Policies\HotelPolicyPolicy;
use App\Policies\ManualPaymentMethodPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\RateCheckPolicy;
use App\Policies\RefundPolicy;
use App\Policies\RolePolicy;
use App\Policies\SearchSessionPolicy;
use App\Policies\SupplierCredentialPolicy;
use App\Policies\SupplierOperationLogPolicy;
use App\Policies\SupplierPolicy;
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
        Gate::policy(Hotel::class, HotelPolicy::class);
        Gate::policy(HotelContact::class, HotelContactPolicy::class);
        Gate::policy(HotelImage::class, HotelImagePolicy::class);
        Gate::policy(HotelPolicyModel::class, HotelPolicyPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(SupplierCredential::class, SupplierCredentialPolicy::class);
        Gate::policy(SupplierOperationLog::class, SupplierOperationLogPolicy::class);
        Gate::policy(SearchSession::class, SearchSessionPolicy::class);
        Gate::policy(RateCheck::class, RateCheckPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(BookingCancellation::class, BookingCancellationPolicy::class);
        Gate::policy(BookingStatusHistory::class, BookingStatusHistoryPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(ManualPaymentMethod::class, ManualPaymentMethodPolicy::class);
        Gate::policy(Voucher::class, DocumentPolicy::class);
        Gate::policy(Invoice::class, DocumentPolicy::class);
        Gate::policy(Receipt::class, DocumentPolicy::class);
        Gate::policy(Refund::class, RefundPolicy::class);

        Gate::define('view_roles', fn (User $user): bool => $user->hasPermissionTo('view_roles'));
        Gate::define('manage_roles', fn (User $user): bool => $user->hasPermissionTo('manage_roles'));
        Gate::define('view_audit_logs', fn (User $user): bool => $user->hasPermissionTo('view_audit_logs'));
        Gate::define('manage_system_settings', fn (User $user): bool => $user->hasPermissionTo('manage_system_settings'));
    }
}
