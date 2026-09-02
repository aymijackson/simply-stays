<?php

use Illuminate\Support\Facades\Route;
use Modules\Services\Http\Controllers\Api\ServicesController;

Route::prefix('v1/services')->name('services.')->group(function () {
    Route::get('/', [ServicesController::class, 'index'])->name('index');
    Route::get('{service}', [ServicesController::class, 'show'])->name('show');
});
