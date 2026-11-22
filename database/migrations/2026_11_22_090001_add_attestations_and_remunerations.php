<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Demande du centre (2026-11-22) :
| - attestation de fin de formation : numéro (ATT-AAAA-NNNN), date et auteur de la délivrance, nombre de duplicatas ;
| - rémunération des formateurs : formation PERSONNALISÉE (en ligne ou en présentiel, formateur attribué à l'inscription :
|   1/3 de la mensualité) ou formation EN GROUPE (formateur du groupe : 20 % du montant) ; versements aux formateurs
|   (jamais supprimés : annulés avec un motif), lus par la caisse.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->string('attestation_number', 20)->nullable()->unique()->after('finished_at');
            $table->timestamp('attestation_issued_at')->nullable()->after('attestation_number');
            $table->foreignId('attestation_issued_by')->nullable()->after('attestation_issued_at')->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('attestation_copies')->default(0)->after('attestation_issued_by');
        });

        Schema::table('inscriptions', function (Blueprint $table) {
            $table->foreignId('formateur_id')->nullable()->after('groupe_id')->constrained('users')->nullOnDelete();
        });

        Schema::create('formateur_versements', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('period', 7);
            $table->unsignedInteger('amount');
            $table->date('paid_on');
            $table->string('method', 20);
            $table->string('reference', 100)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 10)->default('valide');
            $table->string('cancel_reason', 300)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formateur_versements');
        Schema::table('inscriptions', fn (Blueprint $table) => $table->dropConstrainedForeignId('formateur_id'));
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attestation_issued_by');
            $table->dropColumn(['attestation_number', 'attestation_issued_at', 'attestation_copies']);
        });
    }
};
