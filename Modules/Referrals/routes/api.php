<?php

use Illuminate\Support\Facades\Route;
use Modules\Referrals\Http\Controllers\Api\ReferralsController;

Route::middleware('auth:sanctum')->prefix('v1/referrals')->name('referrals.')->group(function () {
    Route::get('/', [ReferralsController::class, 'index'])->name('index');
});
