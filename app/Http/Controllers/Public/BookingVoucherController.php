<?php

namespace App\Http\Controllers\Public;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\PublicSearch\MoneyFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class BookingVoucherController extends Controller
{
    public function __invoke(Request $request, string $booking, MoneyFormatter $money): View|Response
    {
        $bookingModel = Booking::query()
            ->with(['currency', 'rooms', 'guests'])
            ->where('public_uuid', $booking)
            ->firstOrFail();

        abort_if($bookingModel->hasUnresolvedSupplierIdentity(), 409);
        abort_unless(in_array($bookingModel->status, [BookingStatus::Confirmed, BookingStatus::ManualReview], true), 404);

        $view = view('admin.bookings.voucher', [
            'booking' => $bookingModel,
            'money' => $money,
            'issuedAt' => now(),
            'isProvisional' => $bookingModel->status === BookingStatus::ManualReview,
        ]);

        if ($request->boolean('download')) {
            return response($view->render())
                ->header('Content-Type', 'text/html; charset=UTF-8')
                ->header('Content-Disposition', 'attachment; filename="'.$this->filename($bookingModel).'"');
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
