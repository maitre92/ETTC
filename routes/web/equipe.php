<?php

use App\Http\Controllers\Web\Admin\TeamPermissionController;
use Illuminate\Support\Facades\Route;

// Permissions de l'équipe : l'administrateur et ses subalternes (demande du centre, 2026-11-23).
Route::middleware(['auth', 'active', 'password.changed', 'permission:equipe.permissions'])->prefix('equipe')->name('equipe.')->group(function () {
    Route::get('/permissions', [TeamPermissionController::class, 'index'])->name('permissions');
    Route::put('/permissions/{user}', [TeamPermissionController::class, 'update'])->name('permissions.update');
});
