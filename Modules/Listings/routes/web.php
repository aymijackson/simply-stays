<?php

use Illuminate\Support\Facades\Route;
use Modules\Listings\Http\Controllers\Web\HostComplimentaryRuleController;
use Modules\Listings\Http\Controllers\Web\HostListingController;
use Modules\Listings\Http\Controllers\Web\HostPropertyController;
use Modules\Listings\Http\Controllers\Web\ListingsController;

Route::prefix('listings')->name('listings.')->group(function () {
    Route::get('/', [ListingsController::class, 'index'])->name('index');
    Route::get('{listing:slug}', [ListingsController::class, 'show'])->name('show');
});

Route::middleware(['auth', 'role:Host,Admin'])->prefix('host/properties')->name('host.properties.')->group(function () {
    Route::get('/', [HostPropertyController::class, 'index'])->name('index');
    Route::get('create', [HostPropertyController::class, 'create'])->name('create');
    Route::post('/', [HostPropertyController::class, 'store'])->name('store');
    Route::get('{property}/edit', [HostPropertyController::class, 'edit'])->name('edit');
    Route::put('{property}', [HostPropertyController::class, 'update'])->name('update');
    Route::delete('{property}', [HostPropertyController::class, 'destroy'])->name('destroy');
    Route::delete('{property}/photos/{media}', [HostPropertyController::class, 'destroyPhoto'])->name('photos.destroy');
});

Route::middleware(['auth', 'role:Host,Admin'])->prefix('host/listings')->name('host.listings.')->group(function () {
    Route::get('/', [HostListingController::class, 'index'])->name('index');
    Route::get('create', [HostListingController::class, 'create'])->name('create');
    Route::post('/', [HostListingController::class, 'store'])->name('store');
    Route::get('{listing}/edit', [HostListingController::class, 'edit'])->name('edit');
    Route::put('{listing}', [HostListingController::class, 'update'])->name('update');
    Route::delete('{listing}', [HostListingController::class, 'destroy'])->name('destroy');
    Route::patch('{listing}/publish', [HostListingController::class, 'publish'])->name('publish');
    Route::delete('{listing}/photos/{media}', [HostListingController::class, 'destroyPhoto'])->name('photos.destroy');

    Route::get('{listing}/complimentary', [HostComplimentaryRuleController::class, 'index'])->name('complimentary.index');
    Route::post('{listing}/complimentary', [HostComplimentaryRuleController::class, 'store'])->name('complimentary.store');
    Route::delete('{listing}/complimentary/{rule}', [HostComplimentaryRuleController::class, 'destroy'])->name('complimentary.destroy');
});
