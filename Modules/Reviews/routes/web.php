<?php

use Illuminate\Support\Facades\Route;
use Modules\Reviews\Http\Controllers\Web\ListingReviewController;
use Modules\Reviews\Http\Controllers\Web\ReviewsController;
use Modules\Reviews\Http\Controllers\Web\ServiceReviewController;

Route::middleware(['auth', 'role:Admin'])->prefix('reviews')->name('reviews.')->group(function () {
    Route::get('/', [ReviewsController::class, 'index'])->name('index');
    Route::patch('{review}/toggle', [ReviewsController::class, 'toggle'])->name('toggle');
});

Route::middleware('auth')->group(function () {
    Route::get('bookings/{booking}/review', [ListingReviewController::class, 'create'])->name('bookings.review.create');
    Route::post('bookings/{booking}/review', [ListingReviewController::class, 'store'])->name('bookings.review.store');

    Route::get('service-orders/{order}/review', [ServiceReviewController::class, 'create'])->name('service-orders.review.create');
    Route::post('service-orders/{order}/review', [ServiceReviewController::class, 'store'])->name('service-orders.review.store');
});
