<?php

use Illuminate\Support\Facades\Route;
use Modules\Admin\Http\Controllers\Web\AdminActivityController;
use Modules\Admin\Http\Controllers\Web\AdminBookingController;
use Modules\Admin\Http\Controllers\Web\AdminController;
use Modules\Admin\Http\Controllers\Web\AdminEventController;
use Modules\Admin\Http\Controllers\Web\AdminEventSettingController;
use Modules\Admin\Http\Controllers\Web\AdminUserController;

Route::middleware(['auth', 'role:Admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('index');

    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.role');
    Route::patch('users/{user}/status', [AdminUserController::class, 'updateStatus'])->name('users.status');

    Route::get('bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
    Route::post('bookings/{booking}/refund', [AdminBookingController::class, 'refund'])->name('bookings.refund');

    Route::get('activity', [AdminActivityController::class, 'index'])->name('activity.index');

    Route::get('events', [AdminEventController::class, 'index'])->name('events.index');
    Route::patch('events/{event}/approve', [AdminEventController::class, 'approve'])->name('events.approve');
    Route::patch('events/{event}/reject', [AdminEventController::class, 'reject'])->name('events.reject');

    Route::get('events/settings', [AdminEventSettingController::class, 'edit'])->name('events.settings.edit');
    Route::patch('events/settings', [AdminEventSettingController::class, 'update'])->name('events.settings.update');
});
