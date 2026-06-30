<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

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

Schedule::command('ops:scheduler-heartbeat')->everyMinute();
Schedule::command('ops:cleanup')->dailyAt('02:15')->withoutOverlapping();
Schedule::command('hbx:content:sync --resource=hotels --country='.config('travel.hbx.public_country', 'EG').' --last-update-time=yesterday --page-limit=5')
    ->dailyAt('03:30')
    ->withoutOverlapping();
Schedule::command('hbx:content:sync --resource=destinations --country='.config('travel.hbx.public_country', 'EG').' --page-limit=5')
    ->weeklyOn(1, '04:00')
    ->withoutOverlapping();
