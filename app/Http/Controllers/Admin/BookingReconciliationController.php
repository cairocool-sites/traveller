<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Hbx\HbxBookingIdentityService;
use App\Services\Hbx\HbxCertificationEvidenceException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BookingReconciliationController extends Controller
{
    public function __invoke(Booking $booking, HbxBookingIdentityService $identity): View
    {
        Gate::authorize('reconcile', $booking);

        $booking->loadMissing(['supplier', 'currency', 'rateCheck', 'rooms', 'guests', 'searchSession']);
        $audit = $identity->audit($booking);

        return view('admin.bookings.reconciliation', [
            'booking' => $booking,
            'audit' => $audit,
        ]);
    }

    public function resolve(Request $request, Booking $booking, HbxBookingIdentityService $identity): RedirectResponse
    {
        Gate::authorize('reconcile', $booking);

        $validated = $request->validate([
            'supplier_reference' => ['required', 'string', 'max:80'],
            'reason' => ['required', 'string', 'max:500'],
            'confirm' => ['accepted'],
        ]);

        try {
            $identity->correctSupplierReference($booking->loadMissing(['supplier', 'currency', 'rateCheck', 'rooms', 'guests', 'searchSession']), $validated['supplier_reference'], $validated['reason']);
        } catch (HbxCertificationEvidenceException $exception) {
            return back()->withErrors(['supplier_reference' => $exception->getMessage()]);
        }

        return redirect()->route('admin.bookings.reconciliation', $booking);
    }
}
