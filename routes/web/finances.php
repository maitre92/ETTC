<?php

use App\Http\Controllers\Web\Admin\FinanceController;
use Illuminate\Support\Facades\Route;

// Finances et commission (cahier ETTC §5, §8). Toutes les routes portent permission:module.action (jamais role:).
Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::get('/finances', [FinanceController::class, 'index'])->middleware('permission:finances.view')->name('finances.index');
    Route::get('/finances/rapport', [FinanceController::class, 'report'])->middleware('permission:finances.export')->name('finances.report');
    Route::get('/finances/export', [FinanceController::class, 'export'])->middleware('permission:finances.export')->name('finances.export');
    Route::post('/finances/versements', [FinanceController::class, 'payout'])->middleware('permission:finances.payout')->name('finances.payout');
    Route::put('/finances/reglages', [FinanceController::class, 'settings'])->middleware('permission:finances.configure')->name('finances.settings');
});
