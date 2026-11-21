<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Caisse (demande du centre, 2026-11-21) : mouvements saisis à la main — revenus, dépenses, transferts entre moyens
| (espèces, mobile money, banque). Jamais supprimés : annulés avec un motif (audit). Les paiements des apprenants, les
| remboursements et les versements de commission sont lus dans leurs tables (pas recopiés ici).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mouvements_caisse', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->string('type', 10);
            $table->string('category', 30);
            $table->string('label', 200);
            $table->unsignedInteger('amount');
            $table->string('method', 20);
            $table->string('to_method', 20)->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('party', 150)->nullable();
            $table->date('occurred_on');
            $table->string('notes', 1000)->nullable();
            $table->string('proof_path')->nullable();
            $table->string('proof_name')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 10)->default('valide');
            $table->string('cancel_reason', 300)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['occurred_on', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_caisse');
    }
};
