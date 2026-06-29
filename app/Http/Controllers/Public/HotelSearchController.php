<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\SearchSession;
use App\Services\PublicSearch\HotelSearchService;
use App\Services\PublicSearch\MoneyFormatter;
use App\Services\Supplier\Data\HotelDetailsRequestData;
use App\Services\Supplier\Exceptions\SupplierException;
use App\Services\Supplier\SupplierManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HotelSearchController extends Controller
{
    public function index(Request $request): View
    {
        $this->setLocale($request);

        return view('public.hotels.index', [
            'metaTitle' => __('public.search.meta_title'),
            'metaDescription' => __('public.search.meta_description'),
        ]);
    }

    public function search(Request $request, HotelSearchService $search): View|RedirectResponse
    {
        $this->setLocale($request);

        try {
            $session = $request->filled('session')
                ? SearchSession::query()->where('public_uuid', $request->query('session'))->firstOrFail()
                : $search->search($request->query(), $request->session()->getId());
        } catch (ValidationException $exception) {
            return redirect()->route('hotels.index', ['locale' => app()->getLocale()])
                ->withErrors($exception->errors())
                ->withInput($request->query());
        }

        return view('public.hotels.results', [
            'searchSession' => $session,
            'results' => $search->filteredResults($session, $request->query()),
            'filters' => $request->query(),
            'metaTitle' => __('public.results.meta_title', ['destination' => $session->destination_label]),
            'metaDescription' => __('public.results.meta_description', ['destination' => $session->destination_label]),
        ]);
    }

    public function show(string $hotel, Request $request, HotelSearchService $search, SupplierManager $suppliers, MoneyFormatter $money): View
    {
        $this->setLocale($request);

        $canonical = Hotel::query()
            ->with(['translations', 'city.country', 'area', 'facilities.translations', 'images', 'policy'])
            ->where('slug', $hotel)
            ->where('is_active', true)
            ->where('status', 'published')
            ->first();

        $searchSession = null;
        $result = null;
        $supplierDetails = null;
        $warnings = [];

        if (! $canonical && $request->filled('search')) {
            $searchSession = SearchSession::query()->where('public_uuid', $request->query('search'))->first();
            $result = $searchSession ? $search->resultFor($searchSession, $hotel) : null;

            abort_unless($result, 404);

            if ($result['canonical_hotel_id']) {
                $canonical = Hotel::query()
                    ->with(['translations', 'city.country', 'area', 'facilities.translations', 'images', 'policy'])
                    ->whereKey($result['canonical_hotel_id'])
                    ->where('is_active', true)
                    ->where('status', 'published')
                    ->first();
            }

            try {
                $supplierDetails = $suppliers
                    ->resolve($result['supplier_code'] ?? config('travel.public_search.suppliers.0'))
                    ->getHotelDetails(new HotelDetailsRequestData(
                        supplierHotelId: $result['supplier_hotel_id'],
                        destinationIdentifier: $searchSession?->destination_label,
                        locale: app()->getLocale(),
                        currency: $searchSession?->currency ?? config('travel.currency.default'),
                        correlationId: $searchSession?->correlation_id,
                    ));
            } catch (SupplierException) {
                $warnings[] = __('public.details.supplier_details_unavailable');
            }
        }

        abort_unless($canonical || $result, 404);

        return view('public.hotels.show', [
            'canonicalHotel' => $canonical,
            'result' => $result,
            'supplierDetails' => $supplierDetails,
            'searchSession' => $searchSession,
            'warnings' => $warnings,
            'money' => $money,
            'metaTitle' => ($canonical?->translation()?->translated_name ?? $result['name'] ?? __('public.details.meta_title')),
            'metaDescription' => __('public.details.meta_description'),
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
