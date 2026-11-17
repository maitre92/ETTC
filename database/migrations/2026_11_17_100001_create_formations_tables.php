<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Formations et tarifs (cahier ETTC §3.2, §3.3, §5 « Gestion des formations et des tarifs ») — PROPOSITION TECHNIQUE :
//  - niveaux : fiches du catalogue (objectifs, compétences, prérequis), appellations et nombre fixés par le centre ;
//  - formules : options de durée (2 mois, 3 mois…), chacune avec un tarif par mode de formation (en ligne,
//    présentiel ; vide = mode non proposé) et une périodicité de paiement (par mois ou en une fois).
// Montants en FCFA entiers (D-5). Les tarifs se modifient à tout moment dans l'administration (audités) : une
// inscription future devra FIGER le tarif appliqué au moment où elle est prise (calcul de la commission, cahier §8).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('niveaux', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 190)->unique();
            $table->string('summary', 300);
            $table->text('objectives')->nullable();
            $table->text('skills')->nullable();
            $table->text('prerequisites')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_published', 'sort_order']);
        });

        Schema::create('formules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 190)->unique();
            $table->unsignedTinyInteger('duration_months');
            $table->text('points')->nullable();
            $table->unsignedInteger('price_online')->nullable();
            $table->unsignedInteger('price_onsite')->nullable();
            $table->string('billing', 10)->default('mois');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formules');
        Schema::dropIfExists('niveaux');
    }
};
