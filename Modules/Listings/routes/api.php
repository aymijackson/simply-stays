<?php

use Illuminate\Support\Facades\Route;
use Modules\Listings\Http\Controllers\Api\ListingsController;

Route::prefix('v1/listings')->name('listings.')->group(function () {
    Route::get('/', [ListingsController::class, 'index'])->name('index');
    Route::get('{listing:slug}', [ListingsController::class, 'show'])->name('show');
});
