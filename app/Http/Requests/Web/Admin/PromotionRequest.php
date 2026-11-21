<?php

namespace App\Http\Requests\Web\Admin;

use App\Enums\FormationMode;
use App\Models\Promotion;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Promotion sur les frais de formation : automatique (sans code) ou par code promo. */
class PromotionRequest extends ContentRequest
{
    protected function routeKey(): string
    {
        return 'promotion';
    }

    protected function modelClass(): string
    {
        return Promotion::class;
    }

    protected function nullableFields(): array
    {
        return ['code', 'formule_id', 'mode', 'starts_on', 'ends_on', 'max_uses'];
    }

    protected function booleanFields(): array
    {
        return ['is_active'];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $value = $this->input('value');
        $this->merge([
            'code' => Promotion::normalizeCode($this->input('code')),
            'value' => is_string($value) ? (preg_replace('/[^\d]/u', '', $value) ?: null) : $value,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('promotions', 'code')->ignore($this->model()?->id)],
            'kind' => ['required', Rule::in(array_keys(Promotion::KINDS))],
            'value' => ['required', 'integer', 'min:1', Rule::when($this->input('kind') === 'percent', ['max:100'], ['max:100000000'])],
            'formule_id' => ['nullable', Rule::exists('formules', 'id')->whereNull('deleted_at')],
            'mode' => ['nullable', Rule::enum(FormationMode::class)],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'is_active' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->input('kind') === 'percent' && (int) $this->input('value') > 100) {
                $validator->errors()->add('value', 'Un pourcentage ne dépasse pas 100.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Le code ne contient que des lettres, des chiffres, « - » ou « _ » (ex. RENTREE2027).',
            'code.unique' => 'Ce code promo existe déjà.',
            'value.max' => 'Un pourcentage ne dépasse pas 100.',
            'ends_on.after_or_equal' => 'La date de fin doit suivre la date de début.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nom de la promotion', 'code' => 'code promo', 'kind' => 'type de réduction', 'value' => 'réduction',
            'formule_id' => 'formule', 'mode' => 'mode', 'starts_on' => 'début', 'ends_on' => 'fin', 'max_uses' => 'nombre d\'inscriptions',
        ];
    }
}
