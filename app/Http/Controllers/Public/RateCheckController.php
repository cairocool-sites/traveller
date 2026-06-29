<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\RateCheck;
use App\Models\SearchSession;
use App\Services\Booking\BookingFlowException;
use App\Services\Booking\RateCheckService;
use App\Services\PublicSearch\MoneyFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RateCheckController extends Controller
{
    public function store(Request $request, RateCheckService $checks): RedirectResponse
    {
        $this->setLocale($request);

        $validated = $request->validate([
            'search' => ['required', 'uuid'],
            'hotel' => ['required', 'string'],
            'rate' => ['required', 'string'],
            'scenario' => ['nullable', 'string', 'max:80'],
        ]);

        $session = SearchSession::query()->where('public_uuid', $validated['search'])->firstOrFail();

        try {
            $rateCheck = $checks->check($session, $validated['hotel'], $validated['rate'], array_filter(['scenario' => $validated['scenario'] ?? null]));
        } catch (BookingFlowException $exception) {
            return back()->withErrors(['rate' => $exception->getMessage()]);
        }

        if (! $rateCheck->status->allowsBooking()) {
            return back()->withErrors(['rate' => __('public.booking.rate_unavailable')]);
        }

        return redirect()->route('rate-checks.show', ['rateCheck' => $rateCheck->public_uuid, 'locale' => app()->getLocale()]);
    }

    public function show(string $rateCheck, MoneyFormatter $money): View
    {
        $this->setLocale(request());

        $rateCheckModel = RateCheck::query()->with(['searchSession', 'currency'])->where('public_uuid', $rateCheck)->firstOrFail();

        abort_if($rateCheckModel->isExpired() || ! $rateCheckModel->status->allowsBooking(), 404);

        return view('public.rate-checks.show', [
            'rateCheck' => $rateCheckModel,
            'money' => $money,
            'metaTitle' => __('public.booking.guest_details_title'),
            'metaDescription' => __('public.booking.guest_details_title'),
        ]);
    }

    private function setLocale(Request $request): void
    {
        $locale = $request->query('locale', session('public_locale', config('app.locale')));

        if (in_array($locale, config('travel.locales.supported'), true)) {
            app()->setLocale($locale);
            session(['public_locale' => $locale]);
        }
    }
}
