<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\PublicSearch\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function show(string $booking, MoneyFormatter $money, Request $request): View
    {
        $locale = $request->query('locale', session('public_locale', config('app.locale')));

        if (in_array($locale, config('travel.locales.supported'), true)) {
            app()->setLocale($locale);
            session(['public_locale' => $locale]);
        }

        $bookingModel = Booking::query()
            ->with(['rooms', 'guests', 'currency'])
            ->where('public_uuid', $booking)
            ->firstOrFail();

        return view('public.bookings.show', [
            'booking' => $bookingModel,
            'money' => $money,
            'metaTitle' => __('public.booking.confirmation_title'),
            'metaDescription' => __('public.booking.confirmation_title'),
        ]);
    }
}
