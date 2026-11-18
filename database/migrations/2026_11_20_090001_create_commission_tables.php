<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Finances et commission (cahier ETTC §5 « Gestion financière », « Statistiques », §8) :
| - chaque paiement confirmé garde le taux et le montant de la commission du développeur calculés à sa confirmation
|   (frais de formation seulement ; un paiement remboursé ou annulé ne compte plus) : calcul vérifiable ;
| - `commission_versements` : versements de la commission au développeur (mois concerné, montant, référence).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->nullable()->after('amount');
            $table->unsignedInteger('commission_amount')->default(0)->after('commission_rate');
        });

        Schema::create('commission_versements', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7);
            $table->unsignedInteger('amount');
            $table->date('paid_on');
            $table->string('method', 60)->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('period');
        });

        // Paiements déjà confirmés : commission de 20 % sur les formules de formation (règle par défaut du cahier §8).
        DB::table('paiements')->where('status', 'confirme')->where('nature', 'formation')
            ->whereIn('inscription_id', DB::table('inscriptions')->whereNotNull('formule_id')->select('id'))
            ->update(['commission_rate' => 20, 'commission_amount' => DB::raw('ROUND(amount * 0.20)')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_versements');
        Schema::table('paiements', fn (Blueprint $table) => $table->dropColumn(['commission_rate', 'commission_amount']));
    }
};
