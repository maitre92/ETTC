<?php

namespace Database\Factories;

use App\Models\Niveau;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Niveau>
 */
class NiveauFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Niveau '.ucfirst(fake()->unique()->word()),
            'summary' => fake()->sentence(10),
            'objectives' => "Comprendre des phrases simples\nSe présenter",
            'skills' => "Compréhension orale\nExpression écrite",
            'prerequisites' => null,
            'sort_order' => fake()->numberBetween(0, 50),
            'is_published' => true,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
