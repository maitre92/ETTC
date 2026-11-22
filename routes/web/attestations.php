<?php

use App\Http\Controllers\Web\Admin\AttestationController;
use Illuminate\Support\Facades\Route;

// Réglages de l'attestation (demande du centre, 2026-11-22). Toutes les routes portent permission:module.action.
Route::middleware(['auth', 'active', 'password.changed', 'permission:attestations.configure'])->prefix('attestations')->name('attestations.')->group(function () {
    Route::get('/', [AttestationController::class, 'edit'])->name('edit');
    Route::put('/signataire', [AttestationController::class, 'signer'])->name('signer');
    Route::post('/apercu', [AttestationController::class, 'preview'])->name('preview');
    Route::post('/{kind}', [AttestationController::class, 'save'])->whereIn('kind', ['signature', 'stamp'])->name('save');
    Route::delete('/{kind}', [AttestationController::class, 'reset'])->whereIn('kind', ['signature', 'stamp'])->name('reset');
    Route::get('/exemple', [AttestationController::class, 'sample'])->name('sample');
});
