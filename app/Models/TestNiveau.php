<?php

namespace App\Models;

use App\Enums\TestMode;
use App\Enums\TestStatus;
use App\Models\Concerns\LogsActivity;
use Database\Factories\TestNiveauFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Test de niveau d'un candidat (cahier §3.4) : demande, planification, résultat et niveau attribué qui oriente vers la
 * formule appropriée. Le test est GRATUIT (décision du centre, 2026-11-17) : seule l'inscription à une formation est
 * payante.
 */
#[Fillable([
    'number', 'status', 'source', 'name', 'email', 'phone', 'test_mode', 'preferred_slot', 'message', 'client_id',
    'scheduled_at', 'location', 'score', 'niveau_id', 'result_notes', 'result_at', 'result_by', 'cancel_reason',
    'consent_at', 'ip_address', 'user_agent', 'notified_at', 'notification_error',
])]
class TestNiveau extends Model
{
    /** @use HasFactory<TestNiveauFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'tests_niveau';

    protected function casts(): array
    {
        return [
            'status' => TestStatus::class, 'test_mode' => TestMode::class, 'scheduled_at' => 'datetime',
            'result_at' => 'datetime', 'consent_at' => 'datetime', 'notified_at' => 'datetime',
        ];
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class)->withTrashed();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'result_by')->withTrashed();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [TestStatus::Demande->value, TestStatus::Planifie->value]);
    }

    /** Tests d'un apprenant : liés à son compte, ou faits avec son adresse e-mail une fois celle-ci confirmée. */
    public function scopeForClient(Builder $query, Client $client): Builder
    {
        return $query->where(function (Builder $q) use ($client) {
            $q->where('client_id', $client->id);
            if ($client->email && $client->email_verified_at) {
                $q->orWhere('email', $client->email);
            }
        });
    }

    /** « 70 00 00 00 · awa@exemple.ml » */
    public function contactLine(): string
    {
        return collect([$this->phone, $this->email])->filter()->implode(' · ');
    }

    public function activityNoun(): string
    {
        return 'Test de niveau';
    }

    public function activityLabel(): string
    {
        return "{$this->number} — {$this->name}";
    }
}
