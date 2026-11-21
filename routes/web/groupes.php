<?php

use App\Http\Controllers\Web\Admin\GroupeController;
use Illuminate\Support\Facades\Route;

// Séances et groupes (cahier ETTC §3.6, §5). Toutes les routes portent permission:module.action (jamais role:).
Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::get('/groupes', [GroupeController::class, 'index'])->middleware('permission:seances.view')->name('groupes.index');
    Route::post('/groupes', [GroupeController::class, 'store'])->middleware('permission:seances.manage')->name('groupes.store');
    Route::post('/groupes/repartition', [GroupeController::class, 'distribute'])->middleware('permission:seances.manage')->name('groupes.distribute');
    Route::post('/groupes/annonces', [GroupeController::class, 'storeAnnonce'])->middleware('permission:seances.manage')->name('groupes.annonces.store');
    Route::delete('/groupes/annonces/{annonce}', [GroupeController::class, 'destroyAnnonce'])->middleware('permission:seances.manage')->name('groupes.annonces.destroy');
    Route::get('/groupes/{groupe}', [GroupeController::class, 'show'])->middleware('permission:seances.view')->name('groupes.show');
    Route::put('/groupes/{groupe}', [GroupeController::class, 'update'])->middleware('permission:seances.manage')->name('groupes.update');
    Route::post('/groupes/{groupe}/apprenants', [GroupeController::class, 'addMembers'])->middleware('permission:seances.manage')->name('groupes.members.add');
    Route::delete('/groupes/{groupe}/apprenants/{inscription}', [GroupeController::class, 'removeMember'])->middleware('permission:seances.manage')->name('groupes.members.remove');
    Route::post('/groupes/{groupe}/serie', [GroupeController::class, 'planSeries'])->middleware('permission:seances.manage')->name('groupes.series');
    Route::post('/groupes/{groupe}/seances', [GroupeController::class, 'storeSeance'])->middleware('permission:seances.manage')->name('groupes.seances.store');
    Route::put('/groupes/{groupe}/seances/{seance}', [GroupeController::class, 'updateSeance'])->middleware('permission:seances.manage')->name('groupes.seances.update');
    Route::put('/groupes/{groupe}/seances/{seance}/annulation', [GroupeController::class, 'cancelSeance'])->middleware('permission:seances.manage')->name('groupes.seances.cancel');
    Route::post('/groupes/{groupe}/messages', [GroupeController::class, 'storeMessage'])->middleware('permission:seances.manage')->name('groupes.messages.store');
    Route::delete('/groupes/{groupe}/messages/{message}', [GroupeController::class, 'destroyMessage'])->middleware('permission:seances.manage')->name('groupes.messages.destroy');
    Route::post('/groupes/{groupe}/supports', [GroupeController::class, 'storeSupport'])->middleware('permission:seances.supports')->name('groupes.supports.store');
    Route::delete('/groupes/{groupe}/supports/{support}', [GroupeController::class, 'destroySupport'])->middleware('permission:seances.supports')->name('groupes.supports.destroy');
    Route::get('/groupes/{groupe}/supports/{support}', [GroupeController::class, 'downloadSupport'])->middleware('permission:seances.view')->name('groupes.supports.download');
});
