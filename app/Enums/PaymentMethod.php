<?php

namespace App\Enums;

/**
 * Moyen de paiement (décision du centre, 2026-11-18) : EN LIGNE, par mobile money (Orange Money, Wave, Moov Money,
 * chacun avec son numéro marchand réglé dans Paiements → Moyens de paiement ; un moyen sans numéro n'est pas proposé) ;
 * EN PRÉSENTIEL, au centre (espèces, ou mobile money au guichet), enregistré par l'équipe.
 * Paiement automatique par un prestataire (notification serveur, cahier §3.8 A) : comptes marchands À VALIDER.
 */
enum PaymentMethod: string
{
    case Especes = 'especes';
    case OrangeMoney = 'orange_money';
    case Wave = 'wave';
    case MoovMoney = 'moov_money';

    public function label(): string
    {
        return match ($this) {
            self::Especes => 'Espèces (au centre)',
            self::OrangeMoney => 'Orange Money',
            self::Wave => 'Wave',
            self::MoovMoney => 'Moov Money',
        };
    }

    public function isMobileMoney(): bool
    {
        return $this !== self::Especes;
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
