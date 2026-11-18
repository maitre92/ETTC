<?php

namespace App\Services\Demandes;

use App\Enums\Billing;
use App\Enums\DemandeStatus;
use App\Enums\FormationMode;
use App\Enums\InscriptionStatus;
use App\Enums\NeedType;
use App\Enums\PaymentChoice;
use App\Mail\PropositionDemande;
use App\Models\Demande;
use App\Models\Inscription;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Numbering\SequenceService;
use App\Support\PhoneNumber;
use App\Support\SiteSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Proposition chiffrée d'une demande de service (cahier §3.7 formation personnalisée, §3.9 traduction, §3.10 excursion) :
 * l'équipe saisit un montant et un texte, le demandeur reçoit un lien et ACCEPTE d'un clic (« validation de l'offre par
 * le demandeur avant son inscription », §3.7). Une formation personnalisée acceptée devient une inscription au tarif
 * proposé, encaissable comme les autres.
 */
class PropositionService
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly DemandeService $demandes,
        private readonly SequenceService $sequences,
        private readonly SiteSettings $settings,
    ) {}

    public function send(Demande $demande, int $amount, string $text, User $actor): Demande
    {
        if ($demande->accepted_at) {
            throw ValidationException::withMessages(['proposal_amount' => 'Cette proposition a déjà été acceptée.']);
        }
        DB::transaction(function () use ($demande, $amount, $text, $actor) {
            Demande::withoutActivityLog(fn () => $demande->update([
                'proposal_amount' => $amount, 'proposal_text' => $text, 'proposal_sent_at' => now(),
                'proposal_token' => $demande->proposal_token ?? Str::random(48),
            ]));
            $this->logger->log('demandes.proposal_sent', 'demandes', "Proposition envoyée — {$demande->reference()} : ".number_format($amount, 0, ',', ' ').' FCFA', $demande, new: ['amount' => $amount], actor: $actor);
            $this->demandes->changeStatus($demande, DemandeStatus::DevisEnvoye, $actor);
        });

        if ($demande->email) {
            try {
                Mail::to($demande->email, $demande->name)->send(new PropositionDemande($demande, 'offre'));
            } catch (Throwable $e) {
                Log::error("Proposition {$demande->reference()} non envoyée : ".Str::limit($e->getMessage(), 300));
            }
        }

        return $demande;
    }

    /** Le demandeur accepte la proposition depuis son lien (une seule fois). */
    public function accept(Demande $demande): Demande
    {
        if ($demande->accepted_at) {
            return $demande;
        }
        DB::transaction(function () use ($demande) {
            $old = $demande->status;
            Demande::withoutActivityLog(fn () => $demande->update(['accepted_at' => now(), 'status' => DemandeStatus::Gagnee, 'status_changed_at' => now()]));
            $this->logger->log('demandes.proposal_accepted', 'demandes', "Proposition acceptée par {$demande->name} — {$demande->reference()}", $demande, ['status' => $old->value], ['status' => DemandeStatus::Gagnee->value]);
        });

        $team = $this->settings->notificationEmail();
        if ($team) {
            try {
                Mail::to($team)->send(new PropositionDemande($demande, 'acceptee'));
            } catch (Throwable $e) {
                Log::error("Alerte d'acceptation {$demande->reference()} non envoyée : ".Str::limit($e->getMessage(), 300));
            }
        }

        return $demande;
    }

    /** Formation personnalisée acceptée → inscription au tarif proposé (paiement unique), à encaisser ensuite. */
    public function inscribe(Demande $demande, FormationMode $mode, User $actor): Inscription
    {
        if ($demande->need_type !== NeedType::FormationPersonnalisee || ! $demande->proposal_amount) {
            throw ValidationException::withMessages(['mode' => 'Seule une formation personnalisée avec une proposition chiffrée peut devenir une inscription.']);
        }
        if ($existing = Inscription::where('demande_id', $demande->id)->where('status', '!=', InscriptionStatus::Annulee->value)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($demande, $mode, $actor) {
            $inscription = Inscription::create([
                'number' => $this->sequences->next('INS'),
                'status' => InscriptionStatus::Validee,
                'source' => 'centre',
                'demande_id' => $demande->id,
                'client_id' => $demande->client_id,
                'name' => $demande->name,
                'phone' => $demande->phone ? (PhoneNumber::normalize($demande->phone) ?? $demande->phone) : null,
                'email' => $demande->email,
                'formule_id' => null,
                'formule_name' => 'Formation personnalisée',
                'duration_months' => 1,
                'billing' => Billing::Unique,
                'unit_price' => $demande->proposal_amount,
                'total_amount' => $demande->proposal_amount,
                'mode' => $mode,
                'payment_choice' => $mode === FormationMode::EnLigne ? PaymentChoice::EnLigne : PaymentChoice::AuCentre,
                'message' => Str::limit((string) $demande->proposal_text, 2000, ''),
                'validated_at' => now(),
                'validated_by' => $actor->id,
            ]);
            $this->logger->log('inscriptions.created', 'inscriptions', "Inscription (formation personnalisée) depuis la demande {$demande->reference()} — {$inscription->number}", $inscription, actor: $actor);

            return $inscription;
        });
    }
}
