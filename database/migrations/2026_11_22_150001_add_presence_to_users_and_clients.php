<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Présences (demande du centre, 2026-11-22) : le superadmin et l'administrateur voient qui est en ligne, la date et
| l'heure de connexion et la dernière activité — équipe et apprenants. `last_seen_at` : mis à jour au plus une fois par
| minute (TrackLastSeen) ; `last_logout_at` : déconnexion volontaire (hors ligne aussitôt).
*/
return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'clients'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->timestamp('last_seen_at')->nullable()->after('last_login_at');
                $table->timestamp('last_logout_at')->nullable()->after('last_seen_at');
                $table->index('last_seen_at');
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'clients'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['last_seen_at']);
                $table->dropColumn(['last_seen_at', 'last_logout_at']);
            });
        }
    }
};
