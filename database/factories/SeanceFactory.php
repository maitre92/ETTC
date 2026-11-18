<?php

namespace Database\Factories;

use App\Models\Groupe;
use App\Models\Seance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seance>
 */
class SeanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'groupe_id' => Groupe::factory(),
            'starts_at' => now()->addDay()->setTime(18, 0),
            'duration_minutes' => 90,
        ];
    }
}
