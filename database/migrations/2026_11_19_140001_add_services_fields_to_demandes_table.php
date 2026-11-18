<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Formation personnalisée, traduction et excursion au Ghana (cahier ETTC §3.7, §3.9, §3.10) : suivies comme des
| demandes, avec leurs champs propres (langues, type de document, participants), la proposition chiffrée envoyée au
| demandeur et son acceptation par un lien. Une formation personnalisée acceptée devient une inscription (formule libre).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->string('source_language', 60)->nullable()->after('availability');
            $table->string('target_language', 60)->nullable()->after('source_language');
            $table->string('document_type', 120)->nullable()->after('target_language');
            $table->unsignedSmallInteger('participants')->nullable()->after('document_type');
            $table->unsignedInteger('proposal_amount')->nullable()->after('participants');
            $table->text('proposal_text')->nullable()->after('proposal_amount');
            $table->dateTime('proposal_sent_at')->nullable()->after('proposal_text');
            $table->string('proposal_token', 64)->nullable()->unique()->after('proposal_sent_at');
            $table->dateTime('accepted_at')->nullable()->after('proposal_token');
            $table->index('need_type');
        });

        Schema::table('inscriptions', function (Blueprint $table) {
            $table->foreignId('formule_id')->nullable()->change();
            $table->foreignId('demande_id')->nullable()->after('test_niveau_id')->constrained('demandes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('demande_id');
        });
        Schema::table('demandes', function (Blueprint $table) {
            $table->dropIndex(['need_type']);
            $table->dropUnique(['proposal_token']);
            $table->dropColumn(['source_language', 'target_language', 'document_type', 'participants', 'proposal_amount', 'proposal_text', 'proposal_sent_at', 'proposal_token', 'accepted_at']);
        });
    }
};
