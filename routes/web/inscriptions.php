<?php

use App\Http\Controllers\Web\Admin\InscriptionController;
use Illuminate\Support\Facades\Route;

// Inscriptions (cahier ETTC §3.5, §5). Toutes les routes portent permission:module.action (jamais role:).
Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::get('/inscriptions', [InscriptionController::class, 'index'])->middleware('permission:inscriptions.view')->name('inscriptions.index');
    Route::post('/inscriptions', [InscriptionController::class, 'store'])->middleware('permission:inscriptions.create')->name('inscriptions.store');
    Route::post('/inscriptions/depuis-test/{test}', [InscriptionController::class, 'fromTest'])->middleware('permission:inscriptions.create')->name('inscriptions.from-test');
    Route::get('/inscriptions/{inscription}', [InscriptionController::class, 'show'])->middleware('permission:inscriptions.view')->name('inscriptions.show');
    Route::put('/inscriptions/{inscription}/validation', [InscriptionController::class, 'validateFile'])->middleware('permission:inscriptions.validate')->name('inscriptions.validate');
    Route::put('/inscriptions/{inscription}/debut', [InscriptionController::class, 'start'])->middleware('permission:inscriptions.validate')->name('inscriptions.start');
    Route::put('/inscriptions/{inscription}/fin', [InscriptionController::class, 'finish'])->middleware('permission:inscriptions.validate')->name('inscriptions.finish');
    Route::put('/inscriptions/{inscription}/dossier', [InscriptionController::class, 'organize'])->middleware('permission:inscriptions.organize')->name('inscriptions.organize');
    Route::put('/inscriptions/{inscription}/annulation', [InscriptionController::class, 'cancel'])->middleware('permission:inscriptions.cancel')->name('inscriptions.cancel');
    Route::delete('/inscriptions/{inscription}', [InscriptionController::class, 'destroy'])->middleware('permission:inscriptions.delete')->name('inscriptions.destroy');
});
