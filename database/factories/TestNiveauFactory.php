<?php

namespace Database\Factories;

use App\Enums\TestMode;
use App\Enums\TestStatus;
use App\Models\TestNiveau;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestNiveau>
 */
class TestNiveauFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'TST-'.now()->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'status' => TestStatus::Demande,
            'source' => 'site',
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+2237'.fake()->numerify('#######'),
            'test_mode' => TestMode::Centre,
            'fee' => 5000,
            'consent_at' => now(),
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['status' => TestStatus::Planifie, 'scheduled_at' => now()->addDays(2)->setTime(9, 0)]);
    }
}
