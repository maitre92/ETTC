<?php

use App\Http\Controllers\Web\Admin\RemunerationController;
use Illuminate\Support\Facades\Route;

// Rémunération des formateurs (demande du centre, 2026-11-22). Toutes les routes portent permission:module.action.
Route::middleware(['auth', 'active', 'password.changed'])->prefix('remunerations')->name('remunerations.')->group(function () {
    Route::get('/', [RemunerationController::class, 'index'])->middleware('permission:remunerations.view')->name('index');
    Route::post('/versements', [RemunerationController::class, 'pay'])->middleware('permission:remunerations.payout')->name('pay');
    Route::put('/versements/{versement}/annulation', [RemunerationController::class, 'cancel'])->middleware('permission:remunerations.payout')->name('cancel');
    Route::put('/taux', [RemunerationController::class, 'settings'])->middleware('permission:remunerations.configure')->name('settings');
    Route::get('/fiche/{formateur}', [RemunerationController::class, 'sheet'])->middleware('permission:remunerations.view')->name('sheet');
});
