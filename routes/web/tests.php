<?php

use App\Http\Controllers\Web\Admin\TestNiveauController;
use App\Http\Controllers\Web\Admin\TestQuestionController;
use Illuminate\Support\Facades\Route;

// Tests de niveau (cahier ETTC §3.4, §5). Toutes les routes portent permission:module.action (jamais role:).
Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::get('/tests', [TestNiveauController::class, 'index'])->middleware('permission:tests.view')->name('tests.index');
    Route::post('/tests', [TestNiveauController::class, 'store'])->middleware('permission:tests.create')->name('tests.store');
    Route::put('/tests/presentation', [TestNiveauController::class, 'presentation'])->middleware('permission:tests.configure')->name('tests.presentation');
    // Banque de questions du test en ligne (saisie, import d'un fichier via /questions/lot) et réglages.
    Route::get('/questions', [TestQuestionController::class, 'index'])->middleware('permission:tests.configure')->name('questions.index');
    Route::post('/questions', [TestQuestionController::class, 'store'])->middleware('permission:tests.configure')->name('questions.store');
    Route::post('/questions/word', [TestQuestionController::class, 'importWord'])->middleware('permission:tests.configure')->name('questions.word');
    Route::put('/questions/reglages', [TestQuestionController::class, 'settings'])->middleware('permission:tests.configure')->name('questions.settings');
    Route::put('/questions/{question}', [TestQuestionController::class, 'update'])->middleware('permission:tests.configure')->name('questions.update');
    Route::delete('/questions/{question}', [TestQuestionController::class, 'destroy'])->middleware('permission:tests.configure')->name('questions.destroy');
    Route::get('/tests/{test}', [TestNiveauController::class, 'show'])->middleware('permission:tests.view')->name('tests.show');
    Route::get('/tests/{test}/oral/{question}', [TestNiveauController::class, 'recording'])->whereNumber('question')->middleware('permission:tests.view')->name('tests.recording');
    Route::post('/tests/{test}/questionnaire', [TestNiveauController::class, 'questionnaire'])->middleware('permission:tests.schedule')->name('tests.questionnaire');
    Route::get('/tests/{test}/fichier/{which}', [TestNiveauController::class, 'paperFile'])->whereIn('which', ['questionnaire', 'reponses'])->middleware('permission:tests.view')->name('tests.paper-file');
    Route::put('/tests/{test}/formateur', [TestNiveauController::class, 'assignExaminer'])->middleware('permission:tests.schedule')->name('tests.examiner');
    Route::put('/tests/{test}/planification', [TestNiveauController::class, 'schedule'])->middleware('permission:tests.schedule')->name('tests.schedule');
    Route::put('/tests/{test}/resultat', [TestNiveauController::class, 'result'])->middleware('permission:tests.result')->name('tests.result');
    Route::put('/tests/{test}/annulation', [TestNiveauController::class, 'cancel'])->middleware('permission:tests.schedule')->name('tests.cancel');
    Route::delete('/tests/{test}', [TestNiveauController::class, 'destroy'])->middleware('permission:tests.delete')->name('tests.destroy');
});
