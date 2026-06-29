<?php

namespace App\Enums;

enum SupplierOperation: string
{
    case Search = 'search';
    case HotelDetails = 'hotel_details';
    case CheckRate = 'check_rate';
    case Book = 'book';
    case GetBooking = 'get_booking';
    case Cancel = 'cancel';
    case HealthCheck = 'health_check';

    public function capabilityColumn(): string
    {
        return match ($this) {
            self::Search => 'search_enabled',
            self::HotelDetails => 'details_enabled',
            self::CheckRate => 'check_rate_enabled',
            self::Book => 'booking_enabled',
            self::GetBooking => 'booking_lookup_enabled',
            self::Cancel => 'cancellation_enabled',
            self::HealthCheck => 'health_check_enabled',
        };
    }

    public function isAutomaticallyRetryable(): bool
    {
        return in_array($this, [self::Search, self::HotelDetails, self::GetBooking, self::HealthCheck], true);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $case): array => [$case->value => __('admin.suppliers.operations.'.$case->value)]
        )->all();
    }
}
