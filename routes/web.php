<?php

use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\HotelSearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/hotels', [HotelSearchController::class, 'index'])->name('hotels.index');
Route::get('/hotels/search', [HotelSearchController::class, 'search'])->name('hotels.search');
Route::get('/hotels/{hotel}', [HotelSearchController::class, 'show'])->name('hotels.show');
