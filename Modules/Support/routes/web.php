<?php

use Illuminate\Support\Facades\Route;
use Modules\Support\Http\Controllers\Web\SupportChatController;

Route::prefix('support')->name('support.')->group(function () {
    Route::post('start', [SupportChatController::class, 'start'])->name('start');
    Route::get('conversations/{conversation}/messages', [SupportChatController::class, 'messages'])->name('messages');
    Route::post('conversations/{conversation}/messages', [SupportChatController::class, 'store'])->name('messages.store');
});
