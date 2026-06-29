<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\PublicSearch\DestinationLookupService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request, DestinationLookupService $destinations): View
    {
        $this->setLocale($request);

        return view('public.home', [
            'featuredDestinations' => $destinations->featured(app()->getLocale()),
            'metaTitle' => __('public.home.meta_title'),
            'metaDescription' => __('public.home.meta_description'),
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
