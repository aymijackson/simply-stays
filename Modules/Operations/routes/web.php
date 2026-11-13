<?php

use Illuminate\Support\Facades\Route;
use Modules\Operations\Http\Controllers\DashboardController;
use Modules\Operations\Http\Controllers\FinanceController;
use Modules\Operations\Http\Controllers\InventoryController;
use Modules\Operations\Http\Controllers\InventoryItemController;
use Modules\Operations\Http\Controllers\InventoryLocationController;
use Modules\Operations\Http\Controllers\InventoryMovementController;
use Modules\Operations\Http\Controllers\ShiftController;
use Modules\Operations\Http\Controllers\StaffController;

// Literal paths are registered before {wildcard} paths that share a prefix.
Route::middleware('auth')->prefix('operations')->name('ops.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Inventory
    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');

    Route::get('inventory/items/create', [InventoryItemController::class, 'create'])->name('inventory.items.create');
    Route::post('inventory/items', [InventoryItemController::class, 'store'])->name('inventory.items.store');
    Route::get('inventory/items/{item}', [InventoryItemController::class, 'show'])->name('inventory.items.show');
    Route::get('inventory/items/{item}/edit', [InventoryItemController::class, 'edit'])->name('inventory.items.edit');
    Route::put('inventory/items/{item}', [InventoryItemController::class, 'update'])->name('inventory.items.update');
    Route::delete('inventory/items/{item}', [InventoryItemController::class, 'destroy'])->name('inventory.items.destroy');

    Route::get('inventory/locations', [InventoryLocationController::class, 'index'])->name('inventory.locations.index');
    Route::get('inventory/locations/create', [InventoryLocationController::class, 'create'])->name('inventory.locations.create');
    Route::post('inventory/locations', [InventoryLocationController::class, 'store'])->name('inventory.locations.store');
    Route::get('inventory/locations/{location}', [InventoryLocationController::class, 'show'])->name('inventory.locations.show');
    Route::get('inventory/locations/{location}/edit', [InventoryLocationController::class, 'edit'])->name('inventory.locations.edit');
    Route::put('inventory/locations/{location}', [InventoryLocationController::class, 'update'])->name('inventory.locations.update');
    Route::delete('inventory/locations/{location}', [InventoryLocationController::class, 'destroy'])->name('inventory.locations.destroy');
    Route::post('inventory/locations/{location}/count', [InventoryLocationController::class, 'count'])->name('inventory.locations.count');
    Route::post('inventory/locations/{location}/restock', [InventoryLocationController::class, 'restock'])->name('inventory.locations.restock');

    Route::get('inventory/movements', [InventoryMovementController::class, 'index'])->name('inventory.movements.index');
    Route::get('inventory/movements/create', [InventoryMovementController::class, 'create'])->name('inventory.movements.create');
    Route::post('inventory/movements', [InventoryMovementController::class, 'store'])->name('inventory.movements.store');

    // Staff
    Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
    Route::get('staff/create', [StaffController::class, 'create'])->name('staff.create');
    Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
    Route::get('staff/{staff}', [StaffController::class, 'show'])->name('staff.show');
    Route::get('staff/{staff}/edit', [StaffController::class, 'edit'])->name('staff.edit');
    Route::put('staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
    Route::post('staff/{staff}/pay', [StaffController::class, 'pay'])->name('staff.pay');

    // Shifts
    Route::get('shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::get('shifts/attendance', [ShiftController::class, 'attendance'])->name('shifts.attendance');
    Route::get('shifts/create', [ShiftController::class, 'create'])->name('shifts.create');
    Route::post('shifts', [ShiftController::class, 'store'])->name('shifts.store');
    Route::get('shifts/{shift}/edit', [ShiftController::class, 'edit'])->name('shifts.edit');
    Route::put('shifts/{shift}', [ShiftController::class, 'update'])->name('shifts.update');
    Route::delete('shifts/{shift}', [ShiftController::class, 'destroy'])->name('shifts.destroy');
    Route::post('shifts/{shift}/clock-in', [ShiftController::class, 'clockIn'])->name('shifts.clock-in');
    Route::post('shifts/{shift}/clock-out', [ShiftController::class, 'clockOut'])->name('shifts.clock-out');

    // Accounting
    Route::get('finance', [FinanceController::class, 'index'])->name('finance.index');
    Route::get('finance/report', [FinanceController::class, 'report'])->name('finance.report');
    Route::get('finance/export', [FinanceController::class, 'export'])->name('finance.export');
    Route::post('finance/sync-bookings', [FinanceController::class, 'syncBookings'])->name('finance.sync-bookings');
    Route::get('finance/categories', [FinanceController::class, 'categories'])->name('finance.categories');
    Route::post('finance/categories', [FinanceController::class, 'storeCategory'])->name('finance.categories.store');
    Route::delete('finance/categories/{category}', [FinanceController::class, 'destroyCategory'])->name('finance.categories.destroy');
    Route::get('finance/create', [FinanceController::class, 'create'])->name('finance.create');
    Route::post('finance', [FinanceController::class, 'store'])->name('finance.store');
    Route::get('finance/{transaction}/edit', [FinanceController::class, 'edit'])->name('finance.edit');
    Route::put('finance/{transaction}', [FinanceController::class, 'update'])->name('finance.update');
    Route::delete('finance/{transaction}', [FinanceController::class, 'destroy'])->name('finance.destroy');
    Route::get('finance/{transaction}/receipts/{media:uuid}', [FinanceController::class, 'receipt'])->name('finance.receipt');
    Route::delete('finance/{transaction}/receipts/{media:uuid}', [FinanceController::class, 'destroyReceipt'])->name('finance.receipt.destroy');
});
