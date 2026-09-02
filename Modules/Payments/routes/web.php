<?php

use Illuminate\Support\Facades\Route;
use Modules\Payments\Http\Controllers\Web\CheckoutController;
use Modules\Payments\Http\Controllers\Web\PaymentsController;
use Modules\Payments\Http\Controllers\Web\WebhookController;

Route::middleware('auth')->prefix('payments')->name('payments.')->group(function () {
    Route::get('/', [PaymentsController::class, 'index'])->name('index');
});

Route::middleware('auth')->group(function () {
    Route::get('bookings/{booking}/pay', [CheckoutController::class, 'chooseForBooking'])->name('checkout.choose');
    Route::post('bookings/{booking}/pay', [CheckoutController::class, 'initiateForBooking'])->name('checkout.initiate');

    Route::get('service-orders/{serviceOrder}/pay', [CheckoutController::class, 'chooseForServiceOrder'])->name('checkout.choose.service');
    Route::post('service-orders/{serviceOrder}/pay', [CheckoutController::class, 'initiateForServiceOrder'])->name('checkout.initiate.service');

    Route::get('event-bookings/{eventBooking}/pay', [CheckoutController::class, 'chooseForEventBooking'])->name('checkout.choose.event');
    Route::post('event-bookings/{eventBooking}/pay', [CheckoutController::class, 'initiateForEventBooking'])->name('checkout.initiate.event');
});

Route::get('payments/callback/{gateway}', [CheckoutController::class, 'callback'])
    ->whereIn('gateway', ['stripe', 'paystack', 'flutterwave'])
    ->name('payments.callback');

Route::post('payments/webhook/{gateway}', [WebhookController::class, 'handle'])
    ->whereIn('gateway', ['stripe', 'paystack', 'flutterwave'])
    ->name('payments.webhook');
