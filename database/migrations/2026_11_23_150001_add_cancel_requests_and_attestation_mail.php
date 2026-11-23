<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Demande du centre (2026-11-23) :
| - annuler ou rembourser un paiement : l'administrateur seul ; la secrétaire DEMANDE (motif), l'administrateur valide
|   ou refuse ;
| - attestation : date d'envoi à l'apprenant (e-mail, avec le PDF pour une formation en ligne).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->string('cancel_request_type', 15)->nullable()->after('cancel_reason');
            $table->string('cancel_request_reason', 300)->nullable()->after('cancel_request_type');
            $table->foreignId('cancel_requested_by')->nullable()->after('cancel_request_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('cancel_requested_at')->nullable()->after('cancel_requested_by');
        });
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->timestamp('attestation_sent_at')->nullable()->after('attestation_copies');
        });
    }

    public function down(): void
    {
        Schema::table('inscriptions', fn (Blueprint $table) => $table->dropColumn('attestation_sent_at'));
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancel_requested_by');
            $table->dropColumn(['cancel_request_type', 'cancel_request_reason', 'cancel_requested_at']);
        });
    }
};
