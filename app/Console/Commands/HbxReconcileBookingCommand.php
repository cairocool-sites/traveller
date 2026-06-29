<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Booking\BookingReconciliationService;
use Illuminate\Console\Command;

class HbxReconcileBookingCommand extends Command
{
    protected $signature = 'hbx:reconcile-booking {bookingReference}';

    protected $description = 'Safely reconcile an existing local HBX sandbox booking by booking reference.';

    public function handle(BookingReconciliationService $reconciliation): int
    {
        $booking = Booking::query()
            ->with('supplier')
            ->where('booking_reference', $this->argument('bookingReference'))
            ->first();

        if (! $booking || $booking->supplier?->code !== 'hbx_hotels') {
            $this->warn('No local HBX sandbox booking was found for that reference.');

            return self::FAILURE;
        }

        $before = $booking->status->value;
        $booking = $reconciliation->reconcile($booking);

        $this->info('Reconciliation completed.');
        $this->line('Booking: '.$booking->booking_reference);
        $this->line('Status: '.$before.' -> '.$booking->status->value);
        $this->line('Supplier reference present: '.($booking->supplier_booking_reference ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
