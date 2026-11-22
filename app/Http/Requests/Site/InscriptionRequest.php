<?php

namespace App\Http\Requests\Site;

use App\Enums\FormationMode;
use App\Enums\Gender;
use App\Enums\PaymentChoice;
use App\Enums\PaymentMethod;
use App\Models\Promotion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Inscription à une formation (cahier §3.5) : niveau (facultatif : « à déterminer » oriente vers le test gratuit),
 * formule publiée, mode, mode de paiement ; identité (nom, date de naissance, sexe, téléphone, e-mail, adresse).
 * Sur le site, consentement obligatoire ; utilisée aussi par l'équipe (inscription au centre).
 */
class InscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        foreach (['name', 'email', 'phone', 'address', 'city', 'message', 'birth_date', 'gender', 'niveau_id'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim(str_replace("\r\n", "\n", $value));
                $data[$field] = $value === '' ? null : $value;
            }
        }
        if (is_string($this->input('promo_code'))) {
            $data['promo_code'] = Promotion::normalizeCode($this->input('promo_code'));
        }
        $this->merge($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::baseRules() + [
            'consent' => [$this->routeIs('site.*') ? 'accepted' : 'nullable'],
            // Payer en ligne : l'apprenant peut donner tout de suite la référence de sa transaction (facultatif).
            'pay_reference' => ['nullable', 'string', 'min:4', 'max:100'],
            'pay_method' => ['nullable', 'required_with:pay_reference', Rule::enum(PaymentMethod::class)],
            'pay_amount' => ['nullable', 'required_with:pay_reference', 'integer', 'min:1', 'max:100000000'],
            'pay_phone' => ['nullable', 'string', 'max:30'],
            'pay_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    /**
     * Règles communes (site, centre, inscription depuis un test), sans le consentement.
     *
     * @return array<string, mixed>
     */
    public static function baseRules(): array
    {
        return [
            'niveau_id' => ['nullable', Rule::exists('niveaux', 'id')->where('is_published', true)->whereNull('deleted_at')],
            'formule_id' => ['required', Rule::exists('formules', 'id')->where('is_published', true)->whereNull('deleted_at')],
            'mode' => ['required', Rule::enum(FormationMode::class)],
            'payment_choice' => ['required', Rule::enum(PaymentChoice::class)],
            'name' => ['required', 'string', 'max:150'],
            'birth_date' => ['nullable', 'date', 'before:today', 'after:1920-01-01'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 .\-()]{6,28}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:300'],
            'city' => ['nullable', 'string', 'max:100'],
            'message' => ['nullable', 'string', 'max:2000'],
            // Code promo (facultatif) : vérifié au calcul du tarif (Tarification).
            'promo_code' => ['nullable', 'string', 'max:40'],
            // Venu d'une promotion annoncée sur le site (« Je réserve ») : vérifiée au calcul du tarif.
            'promo_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'formule_id.required' => 'Choisissez une formule (durée).',
            'formule_id.exists' => 'Cette formule n\'est plus proposée : choisissez-en une autre.',
            'mode.required' => 'Choisissez le mode : en ligne ou en présentiel.',
            'phone.regex' => 'Ce numéro de téléphone ne semble pas valide (ex. 75 22 91 25).',
            'birth_date.before' => 'La date de naissance doit être dans le passé.',
            'consent.accepted' => 'Merci de confirmer avoir pris connaissance de l\'utilisation de vos données.',
        ];
    }

    public function attributes(): array
    {
        return [
            'pay_reference' => 'référence de la transaction', 'pay_method' => 'moyen utilisé', 'pay_amount' => 'montant envoyé', 'pay_phone' => 'numéro qui a payé', 'pay_proof' => 'capture du paiement',
            'niveau_id' => 'niveau', 'formule_id' => 'formule', 'mode' => 'mode de formation', 'payment_choice' => 'mode de paiement',
            'name' => 'nom complet', 'birth_date' => 'date de naissance', 'gender' => 'sexe', 'phone' => 'téléphone', 'email' => 'e-mail',
            'address' => 'adresse', 'city' => 'ville', 'message' => 'message',
        ];
    }
}
