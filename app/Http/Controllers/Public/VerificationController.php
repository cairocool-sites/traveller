<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\Voucher;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function voucher(string $token): View
    {
        return view('public.verify.show', ['type' => 'voucher', 'document' => Voucher::query()->where('verification_token', $token)->whereNull('revoked_at')->firstOrFail()]);
    }

    public function invoice(string $token): View
    {
        return view('public.verify.show', ['type' => 'invoice', 'document' => Invoice::query()->where('verification_token', $token)->whereNull('revoked_at')->firstOrFail()]);
    }

    public function receipt(string $token): View
    {
        return view('public.verify.show', ['type' => 'receipt', 'document' => Receipt::query()->where('verification_token', $token)->whereNull('revoked_at')->firstOrFail()]);
    }
}
