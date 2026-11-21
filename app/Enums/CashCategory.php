<?php

namespace App\Enums;

/**
 * Catégories des mouvements de caisse saisis à la main (demande du centre, 2026-11-21) : revenus (entrées), dépenses
 * (sorties) et transferts entre moyens (dépôt d'espèces en banque…). Les frais de formation encaissés, les
 * remboursements et les versements de commission entrent dans le journal AUTOMATIQUEMENT : ils ne se saisissent pas ici.
 */
enum CashCategory: string
{
    // Entrées
    case SoldeOuverture = 'solde_ouverture';
    case Traduction = 'traduction';
    case Excursion = 'excursion';
    case Vente = 'vente';
    case Subvention = 'subvention';
    case AutreRevenu = 'autre_revenu';
    // Sorties
    case Salaires = 'salaires';
    case Formateurs = 'formateurs';
    case Loyer = 'loyer';
    case ElectriciteEau = 'electricite_eau';
    case InternetTelephone = 'internet_telephone';
    case Fournitures = 'fournitures';
    case Transport = 'transport';
    case Publicite = 'publicite';
    case Entretien = 'entretien';
    case ImpotsTaxes = 'impots_taxes';
    case AutreDepense = 'autre_depense';
    // Transfert (ni revenu ni dépense)
    case Transfert = 'transfert';

    public function label(): string
    {
        return match ($this) {
            self::SoldeOuverture => 'Solde d\'ouverture (argent déjà en caisse)',
            self::Traduction => 'Traduction',
            self::Excursion => 'Excursion',
            self::Vente => 'Vente (livres, supports…)',
            self::Subvention => 'Subvention, don',
            self::AutreRevenu => 'Autre revenu',
            self::Salaires => 'Salaires du personnel',
            self::Formateurs => 'Paiement des formateurs',
            self::Loyer => 'Loyer',
            self::ElectriciteEau => 'Électricité, eau',
            self::InternetTelephone => 'Internet, téléphone',
            self::Fournitures => 'Fournitures, matériel',
            self::Transport => 'Transport',
            self::Publicite => 'Publicité, communication',
            self::Entretien => 'Entretien, réparations',
            self::ImpotsTaxes => 'Impôts et taxes',
            self::AutreDepense => 'Autre dépense',
            self::Transfert => 'Transfert entre caisses',
        };
    }

    /** entree, sortie ou transfert. */
    public function type(): string
    {
        return match ($this) {
            self::SoldeOuverture, self::Traduction, self::Excursion, self::Vente, self::Subvention, self::AutreRevenu => 'entree',
            self::Transfert => 'transfert',
            default => 'sortie',
        };
    }

    /** @return array<string, string> */
    public static function optionsFor(string $type): array
    {
        return collect(self::cases())->filter(fn (self $c) => $c->type() === $type)->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
