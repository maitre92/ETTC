<?php

use App\Models\Promotion;
use Illuminate\Database\Migrations\Migration;

/*
| Chaque promotion a désormais un code (demande du centre, 2026-11-23) : celles qui n'en avaient pas en reçoivent un,
| généré à partir de leur nom (ex. « promotion du fin d'année » → FINANNEE26).
*/
return new class extends Migration
{
    public function up(): void
    {
        Promotion::withTrashed()->whereNull('code')->get()->each(function (Promotion $promotion) {
            Promotion::withoutActivityLog(fn () => $promotion->forceFill(['code' => Promotion::generateCode((string) $promotion->name)])->saveQuietly());
        });
    }

    public function down(): void
    {
        // Les codes générés restent : ils peuvent déjà avoir été communiqués.
    }
};
