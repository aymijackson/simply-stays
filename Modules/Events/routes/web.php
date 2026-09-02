<?php

use Illuminate\Support\Facades\Route;
use Modules\Events\Http\Controllers\Web\EventBookingController;
use Modules\Events\Http\Controllers\Web\EventsController;
use Modules\Events\Http\Controllers\Web\ManageEventController;

// Registered before the guest events/{event:slug} catch-all below, otherwise
// "manage" would be matched as a slug value by that wildcard route first.
Route::middleware(['auth', 'role:Host,ServiceProvider,Admin'])->prefix('events/manage')->name('events.manage.')->group(function () {
    Route::get('/', [ManageEventController::class, 'index'])->name('index');
    Route::get('create', [ManageEventController::class, 'create'])->name('create');
    Route::post('/', [ManageEventController::class, 'store'])->name('store');
    Route::get('{event}/edit', [ManageEventController::class, 'edit'])->name('edit');
    Route::put('{event}', [ManageEventController::class, 'update'])->name('update');
    Route::patch('{event}/cancel', [ManageEventController::class, 'cancel'])->name('cancel');
});

Route::prefix('events')->name('events.')->group(function () {
    Route::get('/', [EventsController::class, 'index'])->name('index');
    Route::get('calendar', [EventsController::class, 'calendar'])->name('calendar');
    Route::get('{event:slug}', [EventsController::class, 'show'])->name('show');
});

Route::middleware('auth')->prefix('event-bookings')->name('event-bookings.')->group(function () {
    Route::post('occurrences/{occurrence}', [EventBookingController::class, 'store'])->name('store');
    Route::get('{eventBooking}', [EventBookingController::class, 'show'])->name('show');
    Route::patch('{eventBooking}/cancel', [EventBookingController::class, 'cancel'])->name('cancel');
});
