<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Décision du centre (2026-11-17) : le test de niveau est GRATUIT ; seule l'inscription à une formation est payante.
// Le cahier §3.4 prévoyait 5 000 FCFA : frais et encaissement du test sont retirés.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tests_niveau', function (Blueprint $table) {
            $table->dropIndex(['paid_at']);
            $table->dropConstrainedForeignId('payment_recorded_by');
            $table->dropColumn(['fee', 'paid_at', 'payment_method', 'payment_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('tests_niveau', function (Blueprint $table) {
            $table->unsignedInteger('fee')->default(0)->after('location');
            $table->dateTime('paid_at')->nullable()->after('fee');
            $table->string('payment_method', 20)->nullable()->after('paid_at');
            $table->string('payment_reference', 100)->nullable()->after('payment_method');
            $table->foreignId('payment_recorded_by')->nullable()->after('payment_reference')->constrained('users')->nullOnDelete();
            $table->index('paid_at');
        });
    }
};
