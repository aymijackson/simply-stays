<?php

use Illuminate\Support\Facades\Route;
use Modules\Reviews\Http\Controllers\Api\ReviewsController;

Route::prefix('v1/reviews')->name('reviews.')->group(function () {
    Route::get('/', [ReviewsController::class, 'index'])->name('index');
});
