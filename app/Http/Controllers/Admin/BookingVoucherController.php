<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\PublicSearch\MoneyFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class BookingVoucherController extends Controller
{
    public function __invoke(Request $request, Booking $booking, MoneyFormatter $money): View|Response
    {
        Gate::authorize('view', $booking);

        abort_if($booking->hasUnresolvedSupplierIdentity(), 409);

        abort_unless(in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::ManualReview], true), 404);

        $view = view('admin.bookings.voucher', [
            'booking' => $booking->loadMissing(['currency', 'rooms', 'guests']),
            'money' => $money,
            'issuedAt' => now(),
            'isProvisional' => $booking->status === BookingStatus::ManualReview,
        ]);

        if ($request->boolean('download')) {
            return response($view->render())
                ->header('Content-Type', 'text/html; charset=UTF-8')
                ->header('Content-Disposition', 'attachment; filename="'.$this->filename($booking).'"');
        }

        return $view;
    }

    private function filename(Booking $booking): string
    {
        $reference = Str::of($booking->booking_reference ?: 'booking')
            ->replaceMatches('/[^A-Za-z0-9-]+/', '-')
            ->trim('-')
            ->lower()
            ->toString();

        return "cairo-cool-travel-voucher-{$reference}.html";
    }
}
