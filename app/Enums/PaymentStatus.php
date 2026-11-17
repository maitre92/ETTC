<?php

namespace App\Enums;

/**
 * État d'un paiement (cahier §3.8, §5 « impayés, remboursements et annulations ») : en attente (paiement automatique en
 * cours chez l'agrégateur, confirmé seulement par la vérification serveur à serveur), à vérifier (déclaré par l'apprenant),
 * confirmé (argent reçu : reçu délivré), rejeté (déclaration non retrouvée), annulé (erreur de saisie, aucun argent) ou
 * remboursé (argent rendu). Seuls les paiements confirmés comptent dans le montant payé et les recettes.
 */
enum PaymentStatus: string
{
    case EnAttente = 'en_attente';
    case AVerifier = 'a_verifier';
    case Confirme = 'confirme';
    case Rejete = 'rejete';
    case Annule = 'annule';
    case Rembourse = 'rembourse';

    public function label(): string
    {
        return match ($this) {
            self::EnAttente => 'Paiement en ligne en cours',
            self::AVerifier => 'À vérifier',
            self::Confirme => 'Confirmé',
            self::Rejete => 'Rejeté',
            self::Annule => 'Annulé',
            self::Rembourse => 'Remboursé',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::EnAttente => 'bg-light text-dark',
            self::AVerifier => 'bg-warning-subtle',
            self::Confirme => 'bg-success-subtle',
            self::Rejete, self::Annule => 'bg-secondary-subtle',
            self::Rembourse => 'bg-info-subtle',
        };
    }

    /** Un reçu existe : le paiement a été confirmé (même s'il a été remboursé ou annulé ensuite). */
    public function hasReceipt(): bool
    {
        return in_array($this, [self::Confirme, self::Rembourse, self::Annule], true);
    }
}
