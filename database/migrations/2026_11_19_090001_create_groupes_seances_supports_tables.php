<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Séances (cahier ETTC §3.6, §4, §5 « Gestion des séances à distance ») : groupes d'apprenants (niveau, mode, formateur,
| lien Google Meet ou salle, horaire), séances programmées, supports pédagogiques (fichiers privés ou liens).
| `inscriptions.groupe_id` : le groupe de l'apprenant (le libellé `session` reprend le nom du groupe).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groupes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->foreignId('niveau_id')->nullable()->constrained('niveaux')->nullOnDelete();
            $table->string('mode', 20)->default('en_ligne');
            $table->string('teacher', 120)->nullable();
            $table->string('meet_url', 300)->nullable();
            $table->string('location', 200)->nullable();
            $table->string('schedule', 200)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'mode']);
        });

        Schema::create('seances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('groupe_id')->constrained('groupes')->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->unsignedSmallInteger('duration_minutes')->default(90);
            $table->string('title', 200)->nullable();
            $table->string('meet_url', 300)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->boolean('is_cancelled')->default(false);
            $table->string('cancel_reason', 300)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['groupe_id', 'starts_at']);
            $table->index('starts_at');
        });

        Schema::create('supports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('groupe_id')->constrained('groupes')->restrictOnDelete();
            $table->string('title', 200);
            $table->string('kind', 20);
            $table->string('path', 300)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('original_name', 255)->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('inscriptions', function (Blueprint $table) {
            $table->foreignId('groupe_id')->nullable()->after('session')->constrained('groupes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('groupe_id');
        });
        Schema::dropIfExists('supports');
        Schema::dropIfExists('seances');
        Schema::dropIfExists('groupes');
    }
};
