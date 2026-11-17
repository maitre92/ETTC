<?php

use App\Http\Controllers\Web\Admin\PaiementController;
use Illuminate\Support\Facades\Route;

// Paiements (cahier ETTC §3.8, §5). Toutes les routes portent permission:module.action (jamais role:).
Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::get('/paiements', [PaiementController::class, 'index'])->middleware('permission:paiements.view')->name('paiements.index');
    Route::put('/paiements/moyens', [PaiementController::class, 'settings'])->middleware('permission:paiements.configure')->name('paiements.settings');
    Route::post('/inscriptions/{inscription}/paiements', [PaiementController::class, 'store'])->middleware('permission:paiements.record')->name('paiements.store');
    Route::get('/paiements/{paiement}', [PaiementController::class, 'show'])->middleware('permission:paiements.view')->name('paiements.show');
    Route::get('/paiements/{paiement}/recu', [PaiementController::class, 'receipt'])->middleware('permission:paiements.view')->name('paiements.receipt');
    Route::put('/paiements/{paiement}/confirmation', [PaiementController::class, 'confirm'])->middleware('permission:paiements.verify')->name('paiements.confirm');
    Route::put('/paiements/{paiement}/rejet', [PaiementController::class, 'reject'])->middleware('permission:paiements.verify')->name('paiements.reject');
    Route::put('/paiements/{paiement}/annulation', [PaiementController::class, 'cancel'])->middleware('permission:paiements.refund')->name('paiements.cancel');
    Route::put('/paiements/{paiement}/remboursement', [PaiementController::class, 'refund'])->middleware('permission:paiements.refund')->name('paiements.refund');
});
