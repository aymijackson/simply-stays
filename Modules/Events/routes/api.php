<?php

use Illuminate\Support\Facades\Route;
use Modules\Events\Http\Controllers\Api\EventsController;

Route::prefix('v1/events')->name('events.')->group(function () {
    Route::get('/', [EventsController::class, 'index'])->name('index');
});
