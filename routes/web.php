<?php

use App\Http\Controllers\Admin\PaymentEvidenceController;
use App\Http\Controllers\Public\BookingController;
use App\Http\Controllers\Public\CancellationController;
use App\Http\Controllers\Public\DocumentController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\HotelSearchController;
use App\Http\Controllers\Public\PaymentController;
use App\Http\Controllers\Public\RateCheckController;
use App\Http\Controllers\Public\RefundController;
use App\Http\Controllers\Public\VerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/hotels', [HotelSearchController::class, 'index'])->name('hotels.index');
Route::get('/hotels/search', [HotelSearchController::class, 'search'])->name('hotels.search');
Route::post('/rate-checks', [RateCheckController::class, 'store'])->name('rate-checks.store');
Route::get('/rate-checks/{rateCheck}', [RateCheckController::class, 'show'])->name('rate-checks.show');
Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
Route::get('/bookings/{booking}/payment', [PaymentController::class, 'show'])->name('payments.show');
Route::post('/bookings/{booking}/payment', [PaymentController::class, 'store'])->name('payments.store');
Route::get('/bookings/{booking}/cancel', [CancellationController::class, 'show'])->name('cancellations.create');
Route::post('/bookings/{booking}/cancel', [CancellationController::class, 'store'])->middleware('throttle:6,1')->name('cancellations.store');
Route::get('/cancellations/{cancellation}', [CancellationController::class, 'status'])->middleware('throttle:30,1')->name('cancellations.status');
Route::get('/refunds/{refund}', [RefundController::class, 'show'])->middleware('throttle:30,1')->name('refunds.show');
Route::get('/documents/vouchers/{number}', [DocumentController::class, 'voucher'])->name('documents.vouchers.show');
Route::get('/documents/invoices/{number}', [DocumentController::class, 'invoice'])->name('documents.invoices.show');
Route::get('/documents/receipts/{number}', [DocumentController::class, 'receipt'])->name('documents.receipts.show');
Route::get('/verify/voucher/{token}', [VerificationController::class, 'voucher'])->middleware('throttle:30,1')->name('verify.voucher');
Route::get('/verify/invoice/{token}', [VerificationController::class, 'invoice'])->middleware('throttle:30,1')->name('verify.invoice');
Route::get('/verify/receipt/{token}', [VerificationController::class, 'receipt'])->middleware('throttle:30,1')->name('verify.receipt');
Route::get('/admin/payment-evidence/{evidence}', [PaymentEvidenceController::class, 'show'])->middleware('signed')->name('admin.payment-evidence.show');
Route::get('/hotels/{hotel}', [HotelSearchController::class, 'show'])->name('hotels.show');
