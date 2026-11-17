<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Paiements et reçus (cahier ETTC §3.8, §4, §5 « Gestion financière ») : paiement enregistré au centre ou déclaré par
| l'apprenant (mobile money, vérifié par l'équipe), reçu numéroté, nature (frais de formation ; le test est gratuit),
| annulation et remboursement motivés. `inscriptions.amount_paid` = somme des paiements confirmés (impayés).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('inscription_id')->constrained('inscriptions')->restrictOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('nature', 20)->default('formation');
            $table->unsignedInteger('amount');
            $table->string('method', 20);
            $table->string('reference', 100)->nullable();
            $table->string('payer_phone', 30)->nullable();
            $table->string('period', 100)->nullable();
            $table->string('status', 20)->default('a_verifier');
            $table->string('source', 20)->default('centre');
            $table->dateTime('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 1000)->nullable();
            $table->string('cancel_reason', 300)->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'paid_at']);
            $table->index('method');
        });

        Schema::table('inscriptions', function (Blueprint $table) {
            $table->unsignedInteger('amount_paid')->default(0)->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('inscriptions', fn (Blueprint $table) => $table->dropColumn('amount_paid'));
        Schema::dropIfExists('paiements');
    }
};
