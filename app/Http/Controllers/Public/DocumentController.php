<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\Voucher;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function voucher(string $number): View
    {
        return view('public.documents.voucher', ['document' => Voucher::query()->where('voucher_number', $number)->firstOrFail()]);
    }

    public function invoice(string $number): View
    {
        return view('public.documents.invoice', ['document' => Invoice::query()->where('invoice_number', $number)->firstOrFail()]);
    }

    public function receipt(string $number): View
    {
        return view('public.documents.receipt', ['document' => Receipt::query()->where('receipt_number', $number)->firstOrFail()]);
    }
}
