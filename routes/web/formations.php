<?php

use App\Http\Controllers\Web\Admin\FormationController;
use Illuminate\Support\Facades\Route;

// Formations et tarifs (cahier ETTC §3.2, §3.3, §5) : niveaux, formules et tarifs.
// Toutes les routes portent permission:module.action (jamais role:).
Route::middleware(['auth', 'active', 'password.changed'])->prefix('formations')->name('formations.')->group(function () {
    Route::get('/formules', [FormationController::class, 'formules'])->middleware('permission:formations.view')->name('formules.index');
    Route::post('/formules', [FormationController::class, 'storeFormule'])->middleware('permission:formations.create')->name('formules.store');
    Route::put('/formules/{formule}', [FormationController::class, 'updateFormule'])->middleware('permission:formations.update')->name('formules.update');
    Route::delete('/formules/{formule}', [FormationController::class, 'destroyFormule'])->middleware('permission:formations.delete')->name('formules.destroy');

    Route::put('/frais-inscription', [FormationController::class, 'saveFee'])->middleware('permission:formations.update')->name('fee.update');

    // Promotions sur les frais de formation (automatiques ou par code promo).
    Route::get('/promotions', [FormationController::class, 'promotions'])->middleware('permission:formations.view')->name('promotions.index');
    Route::post('/promotions', [FormationController::class, 'storePromotion'])->middleware('permission:formations.create')->name('promotions.store');
    Route::put('/promotions/{promotion}', [FormationController::class, 'updatePromotion'])->middleware('permission:formations.update')->name('promotions.update');
    Route::delete('/promotions/{promotion}', [FormationController::class, 'destroyPromotion'])->middleware('permission:formations.delete')->name('promotions.destroy');

    Route::get('/niveaux', [FormationController::class, 'niveaux'])->middleware('permission:formations.view')->name('niveaux.index');
    Route::post('/niveaux', [FormationController::class, 'storeNiveau'])->middleware('permission:formations.create')->name('niveaux.store');
    Route::put('/niveaux/{niveau}', [FormationController::class, 'updateNiveau'])->middleware('permission:formations.update')->name('niveaux.update');
    Route::delete('/niveaux/{niveau}', [FormationController::class, 'destroyNiveau'])->middleware('permission:formations.delete')->name('niveaux.destroy');
});
