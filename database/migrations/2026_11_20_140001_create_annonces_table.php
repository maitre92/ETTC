<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Annonces du centre aux apprenants (cahier ETTC §4 « Consulter les annonces du centre ») : à tous les apprenants
| (groupe_id nul) ou à un groupe, visibles jusqu'à une date facultative.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annonces', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('body');
            $table->foreignId('groupe_id')->nullable()->constrained('groupes')->nullOnDelete();
            $table->date('expires_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('emailed_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['groupe_id', 'expires_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annonces');
    }
};
