<?php

use Illuminate\Support\Facades\Route;
use Modules\Bookings\Http\Controllers\Web\BookingComplimentaryController;
use Modules\Bookings\Http\Controllers\Web\BookingController;

Route::middleware('auth')->prefix('bookings')->name('bookings.')->group(function () {
    Route::get('/', [BookingController::class, 'index'])->name('index');
    Route::get('new/{listing:slug}', [BookingController::class, 'create'])->name('create');
    Route::post('/', [BookingController::class, 'store'])->name('store');
    Route::get('{booking}', [BookingController::class, 'show'])->name('show');
    Route::patch('{booking}/confirm', [BookingController::class, 'confirm'])->name('confirm');
    Route::patch('{booking}/cancel', [BookingController::class, 'cancel'])->name('cancel');
    Route::post('{booking}/complimentary/{rule}', [BookingComplimentaryController::class, 'store'])->name('complimentary.claim');
});
