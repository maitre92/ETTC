<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Capture du paiement (facultative) jointe par l'apprenant à sa déclaration : fichier privé (disque local), vu par
| l'équipe sur la fiche du paiement et joint à l'e-mail de la personne qui vérifie.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->string('proof_path', 255)->nullable()->after('reference');
            $table->string('proof_name', 255)->nullable()->after('proof_path');
        });
    }

    public function down(): void
    {
        Schema::table('paiements', fn (Blueprint $table) => $table->dropColumn(['proof_path', 'proof_name']));
    }
};
