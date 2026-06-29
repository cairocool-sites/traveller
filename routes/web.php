<?php

use App\Http\Controllers\Public\BookingController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\HotelSearchController;
use App\Http\Controllers\Public\RateCheckController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/hotels', [HotelSearchController::class, 'index'])->name('hotels.index');
Route::get('/hotels/search', [HotelSearchController::class, 'search'])->name('hotels.search');
Route::post('/rate-checks', [RateCheckController::class, 'store'])->name('rate-checks.store');
Route::get('/rate-checks/{rateCheck}', [RateCheckController::class, 'show'])->name('rate-checks.show');
Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
Route::get('/hotels/{hotel}', [HotelSearchController::class, 'show'])->name('hotels.show');
