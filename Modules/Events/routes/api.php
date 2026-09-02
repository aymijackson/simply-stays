<?php

use Illuminate\Support\Facades\Route;
use Modules\Events\Http\Controllers\EventsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('events', EventsController::class)->names('events');
});
