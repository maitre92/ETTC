<?php

namespace App\Models;

use App\Enums\Billing;
use App\Enums\FormationMode;
use App\Enums\Gender;
use App\Enums\InscriptionStatus;
use App\Enums\PaymentChoice;
use App\Enums\PaymentStatus;
use App\Models\Concerns\LogsActivity;
use App\Support\SiteSettings;
use Database\Factories\InscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Inscription d'un apprenant à une formation (cahier §3.5, §4, §5) : niveau, formule, mode, paiement choisi et TARIF
 * FIGÉ au moment de l'inscription (formule, durée, périodicité, prix, total), identité recopiée, session.
 */
#[Fillable([
    'number', 'status', 'source', 'client_id', 'test_niveau_id', 'demande_id', 'name', 'birth_date', 'gender', 'phone', 'email',
    'address', 'city', 'message', 'niveau_id', 'formule_id', 'mode', 'payment_choice', 'formule_name',
    'duration_months', 'billing', 'unit_price', 'formation_amount', 'discount_amount', 'promotion_id', 'promo_code', 'registration_fee', 'total_amount', 'amount_paid', 'session', 'groupe_id', 'formateur_id', 'validated_at', 'validated_by',
    'started_at', 'finished_at', 'attestation_number', 'attestation_issued_at', 'attestation_issued_by', 'attestation_copies', 'cancel_reason', 'internal_notes', 'consent_at', 'ip_address', 'user_agent',
    'notified_at', 'notification_error',
])]
class Inscription extends Model
{
    /** @use HasFactory<InscriptionFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => InscriptionStatus::class, 'mode' => FormationMode::class, 'payment_choice' => PaymentChoice::class,
            'billing' => Billing::class, 'gender' => Gender::class, 'birth_date' => 'date',
            'duration_months' => 'integer', 'unit_price' => 'integer', 'formation_amount' => 'integer', 'discount_amount' => 'integer', 'registration_fee' => 'integer', 'total_amount' => 'integer', 'amount_paid' => 'integer',
            'validated_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'attestation_issued_at' => 'datetime', 'attestation_copies' => 'integer',
            'consent_at' => 'datetime', 'notified_at' => 'datetime',
        ];
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class)->withTrashed();
    }

    public function formule(): BelongsTo
    {
        return $this->belongsTo(Formule::class)->withTrashed();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function testNiveau(): BelongsTo
    {
        return $this->belongsTo(TestNiveau::class)->withTrashed();
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by')->withTrashed();
    }

    public function demande(): BelongsTo
    {
        return $this->belongsTo(Demande::class)->withTrashed();
    }

    public function groupe(): BelongsTo
    {
        return $this->belongsTo(Groupe::class)->withTrashed();
    }

    /** Formateur d'une formation personnalisée (en groupe : celui du groupe, voir teacher()). */
    public function formateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'formateur_id')->withTrashed();
    }

    /** Formation personnalisée (individuelle, en ligne ou en présentiel) : sans formule. */
    public function isPersonalised(): bool
    {
        return $this->formule_id === null;
    }

    /** Formateur qui donne la formation : celui de l'inscription (personnalisée), sinon celui du groupe. */
    public function teacherId(): ?int
    {
        return $this->formateur_id ?? $this->groupe?->formateur_id;
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class)->withTrashed();
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class)->latest('paid_at')->latest('id');
    }

    /** Reste à payer (paiements confirmés seulement). */
    public function balance(): int
    {
        return max(0, $this->total_amount - $this->amount_paid);
    }

    /** « Non payée », « Partiellement payée » ou « Soldée » (cahier §5 « suivre les impayés »). */
    public function paymentState(): string
    {
        return match (true) {
            $this->amount_paid <= 0 && $this->pendingAmount() > 0 => 'Paiement à vérifier',
            $this->amount_paid <= 0 => 'Non payée',
            $this->balance() > 0 => 'Partiellement payée',
            default => 'Soldée',
        };
    }

    public function paymentBadge(): string
    {
        return match ($this->paymentState()) {
            'Non payée' => 'bg-danger-subtle',
            'Paiement à vérifier' => 'bg-warning-subtle',
            'Partiellement payée' => 'bg-warning-subtle',
            default => 'bg-success-subtle',
        };
    }

    /** Frais de formation après réduction (sans les frais d'inscription). */
    public function formationDue(): int
    {
        return max(0, $this->total_amount - $this->registration_fee);
    }

    /** Une échéance de formation : formation réduite ÷ durée (formule au mois), ou tout (paiement unique). */
    public function installment(): int
    {
        $formation = $this->formationDue();

        return $this->billing === Billing::Mensuel && $this->duration_months > 1 ? (int) ceil($formation / $this->duration_months) : $formation;
    }

    /**
     * Montant proposé pour le prochain paiement : les frais d'inscription restants (payés en premier) + une échéance,
     * sans dépasser ce qui reste à encaisser.
     */
    public function nextInstallment(): int
    {
        $covered = $this->amount_paid + $this->pendingAmount();
        $feeDue = max(0, $this->registration_fee - $covered);

        return min($feeDue + $this->installment(), $this->payableNow());
    }

    /**
     * Détail du tarif figé : [libellé, montant, style] (formation, promotion, frais d'inscription, total).
     *
     * @return list<array{0: string, 1: int, 2: string}>
     */
    public function priceLines(): array
    {
        $formation = $this->formation_amount ?: $this->total_amount - $this->registration_fee + $this->discount_amount;
        $lines = [['Frais de formation ('.$this->formule_name.($this->billing === Billing::Mensuel ? ', '.Formule::money($this->unit_price).' × '.$this->duration_months.' mois' : '').')', $formation, '']];
        if ($this->discount_amount > 0) {
            $lines[] = ['Promotion'.($this->promotion ? ' « '.$this->promotion->name.' »' : '').($this->promo_code ? ' (code '.$this->promo_code.')' : ''), -$this->discount_amount, 'promo'];
        }
        if ($this->registration_fee > 0) {
            $lines[] = ["Frais d'inscription (une seule fois)", $this->registration_fee, ''];
        }
        $lines[] = ['Total à payer', $this->total_amount, 'total'];

        return $lines;
    }

    /** Paiements déclarés (en ligne ou par l'apprenant) pas encore vérifiés : ne pas les encaisser une seconde fois. */
    public function pendingAmount(): int
    {
        return (int) $this->hasMany(Paiement::class)->whereIn('status', [PaymentStatus::AVerifier->value, PaymentStatus::EnAttente->value])->sum('amount');
    }

    /** Ce qui reste à encaisser : le reste à payer moins les paiements déjà déclarés et en vérification (le reliquat). */
    public function payableNow(): int
    {
        return max(0, $this->balance() - $this->pendingAmount());
    }

    /** Le paiement déclaré le plus récent encore à vérifier, s'il y en a un. */
    public function pendingPayment(): ?Paiement
    {
        return $this->hasMany(Paiement::class)->where('status', PaymentStatus::AVerifier->value)->latest('id')->first();
    }

    /** Message WhatsApp prêt à envoyer à l'apprenant, adapté à son dossier (rappel du reste à payer, bienvenue…). */
    public function whatsappMessageUrl(): ?string
    {
        $site = app(SiteSettings::class);
        $hello = 'Bonjour '.$this->name.', ici '.$site->get('company.short_name').'. ';
        $payLink = route('client.paiements');
        $message = match (true) {
            $this->status === InscriptionStatus::Annulee => $hello.'Nous revenons vers vous au sujet de votre inscription '.$this->number.'.',
            $this->balance() > 0 && $this->amount_paid === 0 => $hello.'Votre inscription '.$this->number.' ('.$this->formule_name.') est enregistrée. Pour la confirmer, réglez '.Formule::money($this->nextInstallment()).' (total '.Formule::money($this->balance()).'), au centre ou par mobile money : '.$payLink.' Merci !',
            $this->balance() > 0 => $hello.'Petit rappel : il reste '.Formule::money($this->balance()).' à régler pour votre formation ('.$this->number.'), prochaine échéance '.Formule::money($this->nextInstallment()).'. Au centre ou par mobile money : '.$payLink.' Merci !',
            default => $hello.'Votre formation est entièrement réglée. Merci et bonne formation !',
        };

        return $site->whatsappTo($this->phone, $message);
    }

    /** Inscriptions qui ont encore un reste à payer (impayés), hors annulées. */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('status', '!=', InscriptionStatus::Annulee->value)->whereColumn('amount_paid', '<', 'total_amount');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [InscriptionStatus::AValider->value, InscriptionStatus::Validee->value]);
    }

    /** Inscriptions d'un apprenant : liées à son compte, ou faites avec son adresse e-mail une fois celle-ci confirmée. */
    public function scopeForClient(Builder $query, Client $client): Builder
    {
        return $query->where(function (Builder $q) use ($client) {
            $q->where('client_id', $client->id);
            if ($client->email && $client->email_verified_at) {
                $q->orWhere('email', $client->email);
            }
        });
    }

    /** « 100 000 FCFA / mois » ou « 30 000 FCFA (paiement unique) ». */
    public function priceLabel(): string
    {
        return Formule::money($this->unit_price).' '.$this->billing->suffix();
    }

    /** « Débutant · Formule 2 mois · En ligne » */
    public function summary(): string
    {
        return collect([$this->niveau?->name ?? 'Niveau à déterminer', $this->formule_name, $this->mode->label()])->implode(' · ');
    }

    public function contactLine(): string
    {
        return collect([$this->phone, $this->email])->filter()->implode(' · ');
    }

    public function activityNoun(): string
    {
        return 'Inscription';
    }

    public function activityLabel(): string
    {
        return "{$this->number} — {$this->name}";
    }
}
