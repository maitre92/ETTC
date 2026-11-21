<?php

namespace App\Enums;

/**
 * Moyen de paiement (décision du centre, 2026-11-18) : EN LIGNE, par mobile money (Orange Money, Wave, Moov Money,
 * chacun avec son numéro marchand réglé dans Paiements → Moyens de paiement ; un moyen sans numéro n'est pas proposé) ;
 * EN PRÉSENTIEL, au centre : espèces, mobile money au guichet ou BANQUE (virement, versement, chèque — demande du centre,
 * 2026-11-21), enregistré par l'équipe ; une référence est exigée hors espèces. La famille (espèces, mobile money,
 * banque) sert aux soldes de la caisse.
 * Paiement automatique par un prestataire (notification serveur, cahier §3.8 A) : comptes marchands À VALIDER.
 */
enum PaymentMethod: string
{
    case Especes = 'especes';
    case OrangeMoney = 'orange_money';
    case Wave = 'wave';
    case MoovMoney = 'moov_money';
    case Banque = 'banque';

    public function label(): string
    {
        return match ($this) {
            self::Especes => 'Espèces (au centre)',
            self::OrangeMoney => 'Orange Money',
            self::Wave => 'Wave',
            self::MoovMoney => 'Moov Money',
            self::Banque => 'Banque (virement, versement, chèque)',
        };
    }

    /** Libellé court (listes, reçus thermiques, journal de caisse). */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Especes => 'Espèces',
            self::Banque => 'Banque',
            default => $this->label(),
        };
    }

    public function isMobileMoney(): bool
    {
        return in_array($this, [self::OrangeMoney, self::Wave, self::MoovMoney], true);
    }

    /** Famille pour les soldes de caisse : especes, mobile_money, banque. */
    public function family(): string
    {
        return match (true) {
            $this === self::Especes => 'especes',
            $this === self::Banque => 'banque',
            default => 'mobile_money',
        };
    }

    /** Libellé de la référence attendue (null = aucune, espèces). */
    public function referenceLabel(): ?string
    {
        return match (true) {
            $this === self::Especes => null,
            $this === self::Banque => 'N° de virement, de bordereau ou de chèque',
            default => 'Référence de la transaction (SMS reçu)',
        };
    }

    public const FAMILIES = ['especes' => 'Espèces (caisse)', 'mobile_money' => 'Mobile money', 'banque' => 'Banque'];

    /**
     * Choix groupés pour un formulaire : [famille => [valeur => libellé]].
     *
     * @return array<string, array<string, string>>
     */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::cases() as $method) {
            $groups[self::FAMILIES[$method->family()]][$method->value] = $method->shortLabel();
        }

        return $groups;
    }

    /** Réglage du numéro marchand d'un moyen mobile money. */
    public function settingKey(): ?string
    {
        return $this->isMobileMoney() ? 'paiement.'.$this->value : null;
    }

    /** Réglage de l'image du QR marchand (identifiant d'une image de la bibliothèque). */
    public function qrSettingKey(): ?string
    {
        return $this->isMobileMoney() ? 'paiement.'.$this->value.'_qr' : null;
    }

    /** @return list<self> */
    public static function mobileMoney(): array
    {
        return array_values(array_filter(self::cases(), fn (self $m) => $m->isMobileMoney()));
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $m) => [$m->value => $m->label()])->all();
    }
}
