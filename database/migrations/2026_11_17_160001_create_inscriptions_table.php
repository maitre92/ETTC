<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Inscriptions et apprenants (cahier ETTC §3.5, §4, §5 « Gestion des apprenants et des inscriptions ») — PROPOSITION :
//  - inscription en ligne ou au centre : niveau (ou « à déterminer » → test gratuit), formule, mode, paiement choisi ;
//  - TARIF FIGÉ à l'inscription (prix, périodicité, durée, total) : un tarif modifié ensuite ne change pas les
//    inscriptions déjà prises, condition du calcul vérifiable de la commission (cahier §8) ;
//  - identité de l'apprenant (cahier §3.5) recopiée sur l'inscription ; rattachement au compte apprenant facultatif ;
//  - organisation par niveau, formule ou session (libellé de session attribué par le centre).
// Profil apprenant : date de naissance et sexe ajoutés aux comptes (cahier §3.5, §4).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('phone');
            $table->string('gender', 10)->nullable()->after('birth_date');
        });

        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->string('status', 20)->default('a_valider');
            $table->string('source', 20)->default('site');

            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('test_niveau_id')->nullable()->constrained('tests_niveau')->nullOnDelete();
            $table->string('name', 150);
            $table->date('birth_date')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('address', 300)->nullable();
            $table->string('city', 100)->nullable();
            $table->text('message')->nullable();

            $table->foreignId('niveau_id')->nullable()->constrained('niveaux')->restrictOnDelete();
            $table->foreignId('formule_id')->constrained('formules')->restrictOnDelete();
            $table->string('mode', 20);
            $table->string('payment_choice', 20)->default('au_centre');
            // Tarif figé au moment de l'inscription (FCFA entiers).
            $table->string('formule_name', 100);
            $table->unsignedTinyInteger('duration_months');
            $table->string('billing', 10);
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('total_amount');

            $table->string('session', 100)->nullable();
            $table->dateTime('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->string('cancel_reason', 300)->nullable();
            $table->text('internal_notes')->nullable();

            $table->dateTime('consent_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->dateTime('notified_at')->nullable();
            $table->string('notification_error', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index(['niveau_id', 'formule_id', 'mode']);
            $table->index('session');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscriptions');
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['birth_date', 'gender']);
        });
    }
};
