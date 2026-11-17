<?php

namespace Database\Factories;

use App\Enums\Billing;
use App\Models\Formule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Formule>
 */
class FormuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $months = fake()->randomElement([2, 3]);

        return [
            'name' => "Formule {$months} mois ".fake()->unique()->numberBetween(1, 9999),
            'duration_months' => $months,
            'points' => "Formation intensive\nSuivi personnalisé",
            'price_online' => 50000,
            'price_onsite' => 20000,
            'billing' => Billing::Mensuel,
            'sort_order' => fake()->numberBetween(0, 50),
            'is_published' => true,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
