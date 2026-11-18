<?php

namespace Database\Factories;

use App\Enums\FormationMode;
use App\Models\Groupe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Groupe>
 */
class GroupeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Groupe '.fake()->unique()->numberBetween(1, 9999),
            'mode' => FormationMode::EnLigne,
            'teacher' => fake()->name(),
            'meet_url' => 'https://meet.google.com/abc-defg-hij',
            'schedule' => 'Lundi et mercredi, 18h',
            'starts_on' => now()->toDateString(),
            'is_active' => true,
        ];
    }
}
