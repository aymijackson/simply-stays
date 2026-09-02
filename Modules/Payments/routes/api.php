<?php

use Illuminate\Support\Facades\Route;
use Modules\Payments\Http\Controllers\Api\PaymentsController;

Route::prefix('v1/payments')->name('payments.')->group(function () {
    Route::get('/', [PaymentsController::class, 'index'])->name('index');
});
