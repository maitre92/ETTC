<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Demande du centre (2026-11-21) :
| - frais d'inscription (5 000 F par défaut, réglables) FIGÉS sur l'inscription, comme le tarif ;
| - promotions sur les frais de formation : automatiques (période) ou par code promo ; réduction figée sur l'inscription ;
| - groupes : formateur (compte de l'équipe, rôle `formateur`) et capacité, pour répartir automatiquement les apprenants ;
| - discussion de chaque groupe entre le formateur et ses apprenants.
| total_amount reste le TOTAL À PAYER : frais de formation − réduction + frais d'inscription.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 40)->nullable()->unique();
            $table->foreignId('formule_id')->nullable()->constrained('formules')->nullOnDelete();
            $table->string('mode', 20)->nullable();
            $table->string('kind', 10)->default('percent');
            $table->unsignedInteger('value');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('inscriptions', function (Blueprint $table) {
            $table->unsignedInteger('formation_amount')->default(0)->after('unit_price');
            $table->unsignedInteger('discount_amount')->default(0)->after('formation_amount');
            $table->foreignId('promotion_id')->nullable()->after('discount_amount')->constrained('promotions')->nullOnDelete();
            $table->string('promo_code', 40)->nullable()->after('promotion_id');
            $table->unsignedInteger('registration_fee')->default(0)->after('promo_code');
        });
        DB::table('inscriptions')->update(['formation_amount' => DB::raw('total_amount')]);

        Schema::table('groupes', function (Blueprint $table) {
            $table->foreignId('formateur_id')->nullable()->after('teacher')->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('capacity')->nullable()->after('formateur_id');
        });

        Schema::create('groupe_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('groupe_id')->constrained('groupes')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['groupe_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groupe_messages');
        Schema::table('groupes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('formateur_id');
            $table->dropColumn('capacity');
        });
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn(['formation_amount', 'discount_amount', 'promo_code', 'registration_fee']);
        });
        Schema::dropIfExists('promotions');
    }
};
