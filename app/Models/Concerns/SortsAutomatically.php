<?php

namespace App\Models\Concerns;

/**
 * Ordre d'affichage géré tout seul (le centre a demandé de retirer ce champ des écrans) : un nouvel élément se place à
 * la fin (plus grand ordre de sa liste + 10) ; l'ordre existant ne change pas. La liste est celle du même `type` quand
 * le modèle en a un (catégories).
 */
trait SortsAutomatically
{
    public static function bootSortsAutomatically(): void
    {
        static::creating(function ($model) {
            if ($model->sort_order !== null && (int) $model->sort_order > 0) {
                return;
            }
            $query = static::query()->withoutGlobalScopes();
            if (array_key_exists('type', $model->getAttributes())) {
                $query->where('type', $model->type instanceof \BackedEnum ? $model->type->value : $model->type);
            }
            $model->sort_order = ((int) $query->max('sort_order')) + 10;
        });
    }
}
