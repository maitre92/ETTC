<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tests de niveau (cahier ETTC §3.4, §5 « Gestion des tests de niveau ») — PROPOSITION TECHNIQUE :
// demande (site ou centre) → planification (date, au centre ou à distance) → passage (résultat, niveau attribué) ;
// annulation possible. Frais FIGÉS à la demande (`fee`, FCFA) et encaissement noté à part (moyen, référence, qui,
// quand) : ces frais sont distincts des frais de formation et hors commission (cahier §3.4, §8). Jamais de cascade.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tests_niveau', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->string('status', 20)->default('demande');
            $table->string('source', 20)->default('site');

            $table->string('name', 150);
            $table->string('email', 190)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('test_mode', 20)->default('centre');
            $table->string('preferred_slot', 200)->nullable();
            $table->text('message')->nullable();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();

            $table->dateTime('scheduled_at')->nullable();
            $table->string('location', 500)->nullable();

            $table->unsignedInteger('fee')->default(0);
            $table->dateTime('paid_at')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->foreignId('payment_recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('score', 50)->nullable();
            $table->foreignId('niveau_id')->nullable()->constrained('niveaux')->nullOnDelete();
            $table->text('result_notes')->nullable();
            $table->dateTime('result_at')->nullable();
            $table->foreignId('result_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('cancel_reason', 300)->nullable();
            $table->dateTime('consent_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->dateTime('notified_at')->nullable();
            $table->string('notification_error', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'scheduled_at']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tests_niveau');
    }
};
