<?php

namespace App\Http\Requests\Web\Admin;

use App\Enums\Billing;
use App\Models\Formule;
use Illuminate\Validation\Rule;

class FormuleRequest extends ContentRequest
{
    protected function routeKey(): string
    {
        return 'formule';
    }

    protected function modelClass(): string
    {
        return Formule::class;
    }

    protected function nullableFields(): array
    {
        return ['points', 'price_online', 'price_onsite'];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        // « 100 000 », « 100.000 » ou « 100 000 FCFA » : seuls les chiffres comptent.
        foreach (['price_online', 'price_onsite'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $digits = preg_replace('/[^\d]/u', '', $value);
                $this->merge([$field => $digits === '' ? null : $digits]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:24'],
            'points' => ['nullable', 'string', 'max:1000'],
            'price_online' => ['nullable', 'required_without:price_onsite', 'integer', 'min:0', 'max:100000000'],
            'price_onsite' => ['nullable', 'required_without:price_online', 'integer', 'min:0', 'max:100000000'],
            'billing' => ['required', Rule::enum(Billing::class)],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999'],
            'is_published' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'price_online.required_without' => 'Indiquez au moins un tarif : en ligne ou en présentiel.',
            'price_onsite.required_without' => 'Indiquez au moins un tarif : en présentiel ou en ligne.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nom de la formule', 'duration_months' => 'durée (mois)', 'points' => 'points forts',
            'price_online' => 'tarif en ligne', 'price_onsite' => 'tarif en présentiel', 'billing' => 'périodicité',
            'sort_order' => 'ordre d\'affichage',
        ];
    }
}
