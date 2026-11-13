<?php

use Illuminate\Support\Facades\Route;
use Modules\Services\Http\Controllers\Web\ProviderMenuItemController;
use Modules\Services\Http\Controllers\Web\ProviderMenuItemOptionController;
use Modules\Services\Http\Controllers\Web\ProviderMenuItemPairingController;
use Modules\Services\Http\Controllers\Web\ProviderOrderController;
use Modules\Services\Http\Controllers\Web\ProviderPriceListController;
use Modules\Services\Http\Controllers\Web\ProviderQrController;
use Modules\Services\Http\Controllers\Web\ProviderServiceController;
use Modules\Services\Http\Controllers\Web\QrOrderController;
use Modules\Services\Http\Controllers\Web\ServiceOrderController;
use Modules\Services\Http\Controllers\Web\ServicesController;

Route::prefix('services')->name('services.')->group(function () {
    Route::get('/', [ServicesController::class, 'index'])->name('index');
    Route::get('{service}/menu/{menuItem}', [ServicesController::class, 'menuItem'])->name('menu-item');
    Route::get('{service}', [ServicesController::class, 'show'])->name('show');
});

// Ordering from a scanned QR code: no account needed. Placing orders is rate limited.
Route::get('order/{token}', [QrOrderController::class, 'show'])->name('qr.order');
Route::post('order/{token}', [QrOrderController::class, 'store'])->middleware('throttle:8,1')->name('qr.order.store');
Route::get('track/{token}', [QrOrderController::class, 'track'])->name('qr.track');
Route::get('track/{token}/status', [QrOrderController::class, 'status'])->middleware('throttle:60,1')->name('qr.track.status');

Route::middleware('auth')->prefix('service-orders')->name('service-orders.')->group(function () {
    Route::get('/', [ServiceOrderController::class, 'index'])->name('index');
    Route::get('{order}', [ServiceOrderController::class, 'show'])->name('show');
    Route::patch('{order}/cancel', [ServiceOrderController::class, 'cancel'])->name('cancel');
});

Route::middleware('auth')->group(function () {
    Route::get('services/{service}/order', [ServiceOrderController::class, 'create'])->name('service-orders.create');
    Route::post('services/{service}/order', [ServiceOrderController::class, 'store'])->name('service-orders.store');
});

Route::middleware(['auth', 'role:ServiceProvider,Admin'])->prefix('provider/services')->name('provider.services.')->group(function () {
    Route::get('/', [ProviderServiceController::class, 'index'])->name('index');
    Route::get('create', [ProviderServiceController::class, 'create'])->name('create');
    Route::post('/', [ProviderServiceController::class, 'store'])->name('store');
    Route::get('{service}/edit', [ProviderServiceController::class, 'edit'])->name('edit');
    Route::put('{service}', [ProviderServiceController::class, 'update'])->name('update');
    Route::delete('{service}', [ProviderServiceController::class, 'destroy'])->name('destroy');
    Route::patch('{service}/publish', [ProviderServiceController::class, 'publish'])->name('publish');
    Route::delete('{service}/photos/{media:uuid}', [ProviderServiceController::class, 'destroyPhoto'])->name('photos.destroy');
    Route::patch('{service}/photos/{media:uuid}/cover', [ProviderServiceController::class, 'coverPhoto'])->name('photos.cover');
    Route::get('orders', [ProviderOrderController::class, 'index'])->name('orders');

    Route::get('orders/feed', [ProviderOrderController::class, 'feed'])->name('orders.feed');
    Route::patch('orders/seen', [ProviderOrderController::class, 'markSeen'])->name('orders.seen');

    Route::get('{service}/qr', [ProviderQrController::class, 'index'])->name('qr');
    Route::get('{service}/qr/print', [ProviderQrController::class, 'print'])->name('qr.print');
    Route::get('{service}/qr/{code}/download', [ProviderQrController::class, 'download'])->name('qr.download');
    Route::patch('{service}/qr/{code}/toggle', [ProviderQrController::class, 'toggle'])->name('qr.toggle');

    Route::get('{service}/price-list', [ProviderPriceListController::class, 'edit'])->name('price-list.edit');
    Route::put('{service}/price-list', [ProviderPriceListController::class, 'update'])->name('price-list.update');

    Route::get('{service}/menu-items', [ProviderMenuItemController::class, 'index'])->name('menu-items.index');
    Route::post('{service}/menu-items', [ProviderMenuItemController::class, 'store'])->name('menu-items.store');
    Route::put('{service}/menu-items/{menuItem}', [ProviderMenuItemController::class, 'update'])->name('menu-items.update');
    Route::delete('{service}/menu-items/{menuItem}', [ProviderMenuItemController::class, 'destroy'])->name('menu-items.destroy');

    Route::get('{service}/menu-items/{menuItem}/options', [ProviderMenuItemOptionController::class, 'index'])->name('menu-items.options.index');
    Route::post('{service}/menu-items/{menuItem}/option-groups', [ProviderMenuItemOptionController::class, 'storeGroup'])->name('menu-items.option-groups.store');
    Route::delete('{service}/menu-items/{menuItem}/option-groups/{group}', [ProviderMenuItemOptionController::class, 'destroyGroup'])->name('menu-items.option-groups.destroy');
    Route::post('{service}/menu-items/{menuItem}/option-groups/{group}/options', [ProviderMenuItemOptionController::class, 'storeOption'])->name('menu-items.options.store');
    Route::delete('{service}/menu-items/{menuItem}/option-groups/{group}/options/{option}', [ProviderMenuItemOptionController::class, 'destroyOption'])->name('menu-items.options.destroy');

    Route::get('{service}/menu-items/{menuItem}/pairings', [ProviderMenuItemPairingController::class, 'index'])->name('menu-items.pairings.index');
    Route::post('{service}/menu-items/{menuItem}/pairings', [ProviderMenuItemPairingController::class, 'store'])->name('menu-items.pairings.store');
    Route::delete('{service}/menu-items/{menuItem}/pairings/{pairing}', [ProviderMenuItemPairingController::class, 'destroy'])->name('menu-items.pairings.destroy');
});

Route::middleware(['auth', 'role:ServiceProvider,Admin'])->patch('provider/service-orders/{order}/status', [ProviderOrderController::class, 'updateStatus'])
    ->name('provider.service-orders.status');
