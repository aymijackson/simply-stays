<?php

use Illuminate\Support\Facades\Route;
use Modules\Referrals\Http\Controllers\Web\ReferralsController;

Route::middleware(['auth', 'role:Agent,Admin'])->prefix('referrals')->name('referrals.')->group(function () {
    Route::get('/', [ReferralsController::class, 'index'])->name('index');
});
