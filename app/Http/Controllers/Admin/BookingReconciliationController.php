<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Booking\BookingReconciliationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class BookingReconciliationController extends Controller
{
    public function __invoke(Booking $booking, BookingReconciliationService $reconciliation): View
    {
        Gate::authorize('reconcile', $booking);

        $booking->loadMissing(['supplier', 'currency', 'rateCheck']);
        $evidence = $reconciliation->audit($booking);

        return view('admin.bookings.reconciliation', [
            'booking' => $booking,
            'evidence' => $evidence,
        ]);
    }
}
