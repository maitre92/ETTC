<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Promotions mises en avant sur le site (demande du centre, 2026-11-23) : bandeau animé et diaporama ; courte
| description, fiche (affiche) facultative, « Afficher sur le site » (décoché : code réservé, ex. à un partenaire).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('description', 300)->nullable()->after('name');
            $table->foreignId('media_id')->nullable()->after('description')->constrained('medias')->nullOnDelete();
            $table->boolean('show_on_site')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('media_id');
            $table->dropColumn(['description', 'show_on_site']);
        });
    }
};
