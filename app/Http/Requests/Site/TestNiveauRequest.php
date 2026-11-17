<?php

namespace App\Http\Requests\Site;

use App\Enums\TestMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Demande de test de niveau depuis le site (cahier §3.4) : identité, au moins un moyen de contact, mode souhaité,
 * disponibilités ; consentement obligatoire. Utilisée aussi par l'équipe (inscription au centre, sans consentement).
 */
class TestNiveauRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        foreach (['name', 'email', 'phone', 'preferred_slot', 'message'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim(str_replace("\r\n", "\n", $value));
                $data[$field] = $value === '' ? null : $value;
            }
        }
        $this->merge($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:190'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 .\-()]{6,28}$/'],
            'test_mode' => ['required', Rule::enum(TestMode::class)],
            'preferred_slot' => ['nullable', 'string', 'max:200'],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => [$this->routeIs('site.*') ? 'accepted' : 'nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required_without' => 'Indiquez au moins un moyen de vous joindre : e-mail ou téléphone.',
            'phone.required_without' => 'Indiquez au moins un moyen de vous joindre : téléphone ou e-mail.',
            'phone.regex' => 'Ce numéro de téléphone ne semble pas valide (ex. 75 22 91 25).',
            'consent.accepted' => 'Merci de confirmer avoir pris connaissance de l\'utilisation de vos données.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nom complet', 'email' => 'e-mail', 'phone' => 'téléphone', 'test_mode' => 'mode du test', 'preferred_slot' => 'disponibilités', 'message' => 'message'];
    }
}
