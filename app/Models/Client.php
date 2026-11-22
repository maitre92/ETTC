<?php

namespace App\Models;

use App\Enums\Gender;
use App\Models\Concerns\LogsActivity;
use App\Notifications\ClientResetPassword;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Compte d'un apprenant ou client du centre (espace personnel, garde `client`) : suivi de ses demandes, documents
 * mis à sa disposition. Jamais de rôle ni de permission : il ne voit que ce qui lui appartient.
 */
#[Fillable(['name', 'organization', 'email', 'email_verified_at', 'phone', 'birth_date', 'gender', 'password', 'is_active', 'address', 'city', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class Client extends Authenticatable
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, LogsActivity, Notifiable, SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean', 'last_login_at' => 'datetime', 'last_seen_at' => 'datetime', 'last_logout_at' => 'datetime', 'email_verified_at' => 'datetime', 'birth_date' => 'date', 'gender' => Gender::class];
    }

    protected static function booted(): void
    {
        static::saving(function (Client $client) {
            $client->email = mb_strtolower(trim($client->email));
        });
    }

    public function demandes(): HasMany
    {
        return $this->hasMany(Demande::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ClientDocument::class)->latest('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ClientResetPassword($token));
    }

    public function activityExcept(): array
    {
        return ['created_at', 'updated_at', 'deleted_at', 'password', 'remember_token', 'last_login_at', 'last_seen_at', 'last_logout_at'];
    }

    public function activityModule(): string
    {
        return 'clients';
    }

    public function activityNoun(): string
    {
        return 'Client';
    }

    public function activityLabel(): string
    {
        return $this->name.' <'.$this->email.'>';
    }

    /**
     * Mot de passe d'un compte client : simple à retenir (repris du projet CSB, 2026-10-11), 4 caractères au moins
     * (« 1234 » accepté). L'administration garde sa règle forte (8 caractères, lettres et chiffres).
     *
     * @return list<string>
     */
    public static function passwordRules(): array
    {
        return ['string', 'min:4', 'max:255'];
    }
}
