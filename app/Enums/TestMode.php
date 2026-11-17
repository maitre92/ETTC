<?php

namespace App\Enums;

/** Lieu du test de niveau : au centre ou à distance (format défini par le centre, cahier §3.4). */
enum TestMode: string
{
    case Centre = 'centre';
    case Distance = 'distance';

    public function label(): string
    {
        return match ($this) {
            self::Centre => 'Au centre',
            self::Distance => 'À distance',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $m) => [$m->value => $m->label()])->all();
    }
}
