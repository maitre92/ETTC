<?php

use App\Http\Controllers\Web\Admin\AuditController;
use Illuminate\Support\Facades\Route;

// Journal d'activité de l'équipe (administrateur : ses subalternes et lui-même ; superadmin : tout).
Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::get('/journal-activite', [AuditController::class, 'team'])->middleware('permission:activite.view')->name('activite.index');
});
