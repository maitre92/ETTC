<?php

namespace Database\Factories;

use App\Enums\Billing;
use App\Enums\FormationMode;
use App\Enums\InscriptionStatus;
use App\Enums\PaymentChoice;
use App\Models\Formule;
use App\Models\Inscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inscription>
 */
class InscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'INS-'.now()->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'status' => InscriptionStatus::AValider,
            'source' => 'site',
            'name' => fake()->name(),
            'phone' => '+2237'.fake()->numerify('#######'),
            'email' => fake()->unique()->safeEmail(),
            'formule_id' => Formule::factory(),
            'mode' => FormationMode::Presentiel,
            'payment_choice' => PaymentChoice::AuCentre,
            'formule_name' => 'Formule 2 mois',
            'duration_months' => 2,
            'billing' => Billing::Mensuel,
            'unit_price' => 50000,
            'total_amount' => 100000,
            'consent_at' => now(),
        ];
    }
}
