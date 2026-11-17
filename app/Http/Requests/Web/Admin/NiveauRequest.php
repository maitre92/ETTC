<?php

namespace App\Http\Requests\Web\Admin;

use App\Models\Niveau;

class NiveauRequest extends ContentRequest
{
    protected function routeKey(): string
    {
        return 'niveau';
    }

    protected function modelClass(): string
    {
        return Niveau::class;
    }

    protected function nullableFields(): array
    {
        return ['objectives', 'skills', 'prerequisites'];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'summary' => ['required', 'string', 'max:300'],
            'objectives' => ['nullable', 'string', 'max:3000'],
            'skills' => ['nullable', 'string', 'max:3000'],
            'prerequisites' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_published' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nom du niveau', 'summary' => 'résumé', 'objectives' => 'objectifs pédagogiques',
            'skills' => 'compétences visées', 'prerequisites' => 'prérequis', 'sort_order' => 'ordre d\'affichage',
        ];
    }
}
