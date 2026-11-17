<?php

use App\Http\Controllers\Web\Admin\CategoryController;
use Illuminate\Support\Facades\Route;

// Catégories des actualités : la permission de modification du module
// concerné, vérifiée par CategoryPolicy selon la famille.
Route::middleware(['auth', 'active', 'password.changed', 'permission:articles.update'])->group(function () {
    Route::get('/categories/{type}', [CategoryController::class, 'index'])->whereIn('type', ['article'])->name('categories.index');
    Route::post('/categories/{type}', [CategoryController::class, 'store'])->whereIn('type', ['article'])->name('categories.store');
    Route::put('/categories/element/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/element/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
});
