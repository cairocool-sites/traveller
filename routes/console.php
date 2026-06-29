<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bookings:expire-drafts', function (): int {
    $count = Booking::query()
        ->whereIn('status', [
            BookingStatus::Draft->value,
            BookingStatus::PendingRateCheck->value,
            BookingStatus::RateConfirmed->value,
            BookingStatus::GuestDetailsCompleted->value,
        ])
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->update(['status' => BookingStatus::Expired->value]);

    $this->info("Expired {$count} booking drafts.");

    return self::SUCCESS;
})->purpose('Expire stale booking drafts without touching confirmed supplier bookings');
