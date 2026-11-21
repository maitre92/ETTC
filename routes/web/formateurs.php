<?php

use App\Http\Controllers\Web\Admin\FormateurController;
use Illuminate\Support\Facades\Route;

// Espace formateur (demande du centre, 2026-11-21) : ses groupes seulement. Permission formateurs.space (jamais role:) ;
// le contrôleur vérifie que le groupe est bien le sien.
Route::middleware(['auth', 'active', 'password.changed', 'permission:formateurs.space'])->prefix('mon-espace-formateur')->name('formateur.')->group(function () {
    Route::get('/', [FormateurController::class, 'index'])->name('index');
    Route::get('/groupes/{groupe}', [FormateurController::class, 'show'])->name('groupes.show');
    Route::post('/groupes/{groupe}/messages', [FormateurController::class, 'storeMessage'])->name('groupes.messages.store');
    Route::delete('/groupes/{groupe}/messages/{message}', [FormateurController::class, 'destroyMessage'])->name('groupes.messages.destroy');
    Route::post('/groupes/{groupe}/seances', [FormateurController::class, 'storeSeance'])->name('groupes.seances.store');
    Route::put('/groupes/{groupe}/seances/{seance}/annulation', [FormateurController::class, 'cancelSeance'])->name('groupes.seances.cancel');
    Route::post('/groupes/{groupe}/supports', [FormateurController::class, 'storeSupport'])->name('groupes.supports.store');
    Route::delete('/groupes/{groupe}/supports/{support}', [FormateurController::class, 'destroySupport'])->name('groupes.supports.destroy');
    Route::get('/groupes/{groupe}/supports/{support}', [FormateurController::class, 'downloadSupport'])->name('groupes.supports.download');
});
