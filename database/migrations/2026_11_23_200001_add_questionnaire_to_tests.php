<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Questionnaire en document (PDF, Word…) pour un test au centre ou à distance (demande du centre, 2026-11-23) : déposé
| par l'équipe, téléchargé par le candidat (lien de sa convocation), qui renvoie ses réponses en fichier. Fichiers privés.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tests_niveau', function (Blueprint $table) {
            $table->string('questionnaire_path')->nullable()->after('online_started_at');
            $table->string('questionnaire_name')->nullable()->after('questionnaire_path');
            $table->string('answers_path')->nullable()->after('questionnaire_name');
            $table->string('answers_name')->nullable()->after('answers_path');
            $table->timestamp('answers_at')->nullable()->after('answers_name');
        });
    }

    public function down(): void
    {
        Schema::table('tests_niveau', fn (Blueprint $table) => $table->dropColumn(['questionnaire_path', 'questionnaire_name', 'answers_path', 'answers_name', 'answers_at']));
    }
};
