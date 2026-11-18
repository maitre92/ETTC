<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Versement de la commission au développeur (cahier §8 : « commissions déjà versées »), rattaché à un mois. */
#[Fillable(['period', 'amount', 'paid_on', 'method', 'reference', 'notes', 'recorded_by'])]
class CommissionVersement extends Model
{
    use LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return ['amount' => 'integer', 'paid_on' => 'date'];
    }

    public function activityNoun(): string
    {
        return 'Versement de commission';
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }
}
