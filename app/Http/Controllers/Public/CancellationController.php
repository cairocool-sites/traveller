<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Services\Cancellation\CancellationEligibilityService;
use App\Services\Cancellation\CancellationFlowException;
use App\Services\Cancellation\CancellationService;
use App\Services\PublicSearch\MoneyFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CancellationController extends Controller
{
    public function show(string $booking, CancellationEligibilityService $eligibility, MoneyFormatter $money): View
    {
        $bookingModel = Booking::query()->with('currency')->where('public_uuid', $booking)->firstOrFail();

        return view('public.cancellations.show', [
            'booking' => $bookingModel,
            'eligibility' => $eligibility->evaluate($bookingModel),
            'money' => $money,
            'metaTitle' => __('public.cancellations.title'),
            'metaDescription' => __('public.cancellations.title'),
        ]);
    }

    public function store(string $booking, Request $request, CancellationService $cancellations): RedirectResponse
    {
        $bookingModel = Booking::query()->where('public_uuid', $booking)->firstOrFail();
        $validated = $request->validate([
            'customer_reason' => ['nullable', 'string', 'max:1000'],
            'confirm' => ['accepted'],
            'acknowledge_non_refundable' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $cancellation = $cancellations->request($bookingModel, $validated + ['idempotency_key' => $validated['idempotency_key'] ?? (string) Str::uuid()]);
        } catch (CancellationFlowException $exception) {
            return back()->withErrors(['cancellation' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('cancellations.status', ['cancellation' => $cancellation->public_uuid, 'locale' => app()->getLocale()]);
    }

    public function status(string $cancellation, MoneyFormatter $money): View
    {
        $cancellationModel = BookingCancellation::query()->with(['booking', 'currency'])->where('public_uuid', $cancellation)->firstOrFail();

        return view('public.cancellations.status', [
            'cancellation' => $cancellationModel,
            'money' => $money,
            'metaTitle' => __('public.cancellations.status_title'),
            'metaDescription' => __('public.cancellations.status_title'),
        ]);
    }
}
