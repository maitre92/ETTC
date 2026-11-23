<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Demande du centre (2026-11-23) :
| - attestation subordonnée au paiement intégral et à la FIN PRÉVUE de la formation (modifiable) : inscriptions.planned_end_on ;
| - test de niveau EN LIGNE, instantané, ouvert à tout visiteur : banque de questions (QCM), score → niveau
|   (niveaux.min_score), réponses gardées sur le test ;
| - test confié à un FORMATEUR (examiner_id) : les frais d'inscription de l'inscription qui suit lui reviennent.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->date('planned_end_on')->nullable()->after('finished_at');
        });

        Schema::table('niveaux', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_score')->nullable()->after('prerequisites');
        });

        Schema::create('test_questions', function (Blueprint $table) {
            $table->id();
            $table->string('section', 20);
            $table->unsignedTinyInteger('difficulty')->default(1);
            $table->text('question');
            $table->text('choices');
            $table->unsignedTinyInteger('answer');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'section']);
        });

        Schema::table('tests_niveau', function (Blueprint $table) {
            $table->foreignId('examiner_id')->nullable()->after('location')->constrained('users')->nullOnDelete();
            $table->string('online_token', 64)->nullable()->unique()->after('examiner_id');
            $table->text('online_questions')->nullable()->after('online_token');
            $table->text('online_answers')->nullable()->after('online_questions');
            $table->timestamp('online_started_at')->nullable()->after('online_answers');
        });
    }

    public function down(): void
    {
        Schema::table('tests_niveau', function (Blueprint $table) {
            $table->dropConstrainedForeignId('examiner_id');
            $table->dropColumn(['online_token', 'online_questions', 'online_answers', 'online_started_at']);
        });
        Schema::dropIfExists('test_questions');
        Schema::table('niveaux', fn (Blueprint $table) => $table->dropColumn('min_score'));
        Schema::table('inscriptions', fn (Blueprint $table) => $table->dropColumn('planned_end_on'));
    }
};
