<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ManualPaymentMethod;
use App\Services\Payment\PaymentFlowException;
use App\Services\Payment\PaymentService;
use App\Services\PublicSearch\MoneyFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function show(string $booking, MoneyFormatter $money): View
    {
        $bookingModel = Booking::query()->with(['currency', 'payments'])->where('public_uuid', $booking)->firstOrFail();

        return view('public.payments.show', [
            'booking' => $bookingModel,
            'methods' => ManualPaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'money' => $money,
            'metaTitle' => __('public.payments.title'),
            'metaDescription' => __('public.payments.title'),
        ]);
    }

    public function store(string $booking, Request $request, PaymentService $payments): RedirectResponse
    {
        $bookingModel = Booking::query()->where('public_uuid', $booking)->firstOrFail();
        $method = ManualPaymentMethod::query()->whereKey($request->input('manual_payment_method_id'))->where('is_active', true)->firstOrFail();

        $request->validate([
            'submitted_reference' => ['nullable', 'string', 'max:120'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
            'evidence' => ['nullable', 'file', 'max:'.config('travel.payments.evidence_max_kilobytes'), 'mimes:'.implode(',', config('travel.payments.evidence_mimes'))],
        ]);

        try {
            $payment = $payments->submit($bookingModel, $method, $request->only(['submitted_reference', 'customer_notes']), $request->file('evidence'));
        } catch (PaymentFlowException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('payments.show', ['booking' => $bookingModel->public_uuid, 'locale' => app()->getLocale()])
            ->with('status', __('public.payments.submitted', ['reference' => $payment->public_uuid]));
    }
}
