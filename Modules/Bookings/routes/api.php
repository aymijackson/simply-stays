<?php

use Illuminate\Support\Facades\Route;
use Modules\Bookings\Http\Controllers\Api\BookingsController;

Route::middleware('auth:sanctum')->prefix('v1/bookings')->name('bookings.')->group(function () {
    Route::get('/', [BookingsController::class, 'index'])->name('index');
    Route::post('/', [BookingsController::class, 'store'])->name('store');
    Route::get('{booking}', [BookingsController::class, 'show'])->name('show');
});
