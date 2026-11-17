<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentNature;
use App\Enums\PaymentStatus;
use App\Models\Inscription;
use App\Models\Paiement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Paiement>
 */
class PaiementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'REC-'.now()->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'inscription_id' => Inscription::factory(),
            'nature' => PaymentNature::Formation,
            'amount' => 20000,
            'method' => PaymentMethod::OrangeMoney,
            'reference' => 'MP'.fake()->unique()->numerify('##########'),
            'status' => PaymentStatus::AVerifier,
            'source' => 'apprenant',
            'paid_at' => now(),
        ];
    }
}
