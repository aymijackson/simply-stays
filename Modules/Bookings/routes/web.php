<?php

use Illuminate\Support\Facades\Route;
use Modules\Bookings\Http\Controllers\Web\BookingComplimentaryController;
use Modules\Bookings\Http\Controllers\Web\BookingController;
use Modules\Bookings\Http\Controllers\Web\GuestRegistrationController;

Route::middleware('auth')->prefix('bookings')->name('bookings.')->group(function () {
    Route::get('/', [BookingController::class, 'index'])->name('index');
    Route::get('new/{listing:slug}', [BookingController::class, 'create'])->name('create');
    Route::post('/', [BookingController::class, 'store'])->name('store');
    Route::get('{booking}', [BookingController::class, 'show'])->name('show');
    Route::patch('{booking}/confirm', [BookingController::class, 'confirm'])->name('confirm');
    Route::patch('{booking}/cancel', [BookingController::class, 'cancel'])->name('cancel');
    Route::post('{booking}/complimentary/{rule}', [BookingComplimentaryController::class, 'store'])->name('complimentary.claim');
});

Route::middleware('auth')->prefix('guest-forms')->name('guest-forms.')->group(function () {
    Route::get('/', [GuestRegistrationController::class, 'index'])->name('index');
    Route::get('create', [GuestRegistrationController::class, 'create'])->name('create');
    Route::post('/', [GuestRegistrationController::class, 'store'])->name('store');
    Route::get('{guestRegistration}', [GuestRegistrationController::class, 'show'])->name('show');
    Route::get('{guestRegistration}/edit', [GuestRegistrationController::class, 'edit'])->name('edit');
    Route::put('{guestRegistration}', [GuestRegistrationController::class, 'update'])->name('update');
    Route::delete('{guestRegistration}', [GuestRegistrationController::class, 'destroy'])->name('destroy');
    Route::post('{guestRegistration}/scans', [GuestRegistrationController::class, 'storeScans'])->name('scans.store');
    Route::get('{guestRegistration}/scans/{media}', [GuestRegistrationController::class, 'showScan'])->name('scans.show');
    Route::delete('{guestRegistration}/scans/{media}', [GuestRegistrationController::class, 'destroyScan'])->name('scans.destroy');
});
