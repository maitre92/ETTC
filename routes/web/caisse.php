<?php

use App\Http\Controllers\Web\Admin\CaisseController;
use Illuminate\Support\Facades\Route;

// Caisse (demande du centre, 2026-11-21). Toutes les routes portent permission:module.action (jamais role:).
Route::middleware(['auth', 'active', 'password.changed'])->prefix('caisse')->name('caisse.')->group(function () {
    Route::get('/', [CaisseController::class, 'index'])->middleware('permission:caisse.view')->name('index');
    Route::post('/', [CaisseController::class, 'store'])->middleware('permission:caisse.record')->name('store');
    Route::put('/{mouvement}/annulation', [CaisseController::class, 'cancel'])->middleware('permission:caisse.cancel')->name('cancel');
    Route::get('/{mouvement}/justificatif', [CaisseController::class, 'proof'])->middleware('permission:caisse.view')->name('proof');
    Route::get('/export/excel', [CaisseController::class, 'export'])->middleware('permission:caisse.export')->name('export');
    Route::get('/export/pdf', [CaisseController::class, 'report'])->middleware('permission:caisse.export')->name('report');
});
