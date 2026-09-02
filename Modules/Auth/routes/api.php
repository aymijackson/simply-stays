<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\Api\AuthController;

Route::prefix('v1/auth')->name('auth.')->group(function () {
    Route::middleware('throttle:6,1')->post('register', [AuthController::class, 'register'])->name('register');
    Route::middleware('throttle:6,1')->post('login', [AuthController::class, 'login'])->name('login');
    Route::middleware('auth:sanctum')->post('logout', [AuthController::class, 'logout'])->name('logout');
});
