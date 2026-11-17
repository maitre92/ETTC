<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Paiement automatique par un agrégateur agréé (cahier §3.8 A) : prestataire, identifiant de la transaction chez lui
| (unique), dernier état connu et date de la dernière vérification serveur à serveur.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->string('gateway', 30)->nullable()->after('source');
            $table->string('gateway_transaction_id', 64)->nullable()->unique()->after('gateway');
            $table->string('gateway_status', 40)->nullable()->after('gateway_transaction_id');
            $table->dateTime('gateway_checked_at')->nullable()->after('gateway_status');
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropUnique(['gateway_transaction_id']);
            $table->dropColumn(['gateway', 'gateway_transaction_id', 'gateway_status', 'gateway_checked_at']);
        });
    }
};
