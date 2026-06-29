<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Services\PublicSearch\MoneyFormatter;
use Illuminate\View\View;

class RefundController extends Controller
{
    public function show(string $refund, MoneyFormatter $money): View
    {
        $refundModel = Refund::query()->with(['booking', 'currency'])->where('public_uuid', $refund)->firstOrFail();

        return view('public.refunds.show', [
            'refund' => $refundModel,
            'money' => $money,
            'metaTitle' => __('public.refunds.title'),
            'metaDescription' => __('public.refunds.title'),
        ]);
    }
}
