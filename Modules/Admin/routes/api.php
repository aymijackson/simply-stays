<?php

use Illuminate\Support\Facades\Route;
use Modules\Admin\Http\Controllers\Api\AdminController;

Route::prefix('v1/admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('index');
});
