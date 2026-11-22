<?php

use App\Http\Controllers\Web\Admin\ConnexionController;
use Illuminate\Support\Facades\Route;

// Qui est en ligne (demande du centre, 2026-11-22).
Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::get('/connexions', [ConnexionController::class, 'index'])->middleware('permission:connexions.view')->name('connexions.index');
});
