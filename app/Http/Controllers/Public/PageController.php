<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(Request $request, string $page): View
    {
        $this->setLocale($request);

        abort_unless(array_key_exists($page, self::pages()), 404);

        return view('public.page', [
            'pageKey' => $page,
            'metaTitle' => __('public.pages.'.$page.'.meta_title'),
            'metaDescription' => __('public.pages.'.$page.'.meta_description'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function pages(): array
    {
        return [
            'about' => '/about',
            'contact' => '/contact',
            'terms' => '/terms',
            'privacy' => '/privacy',
            'payment-policy' => '/payment-policy',
            'cancellation-policy' => '/cancellation-policy',
            'support' => '/support',
        ];
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
