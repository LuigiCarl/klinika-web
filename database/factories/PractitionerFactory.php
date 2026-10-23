<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Practitioner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Practitioner>
 */
class PractitionerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'display_name' => fake()->name(),
            'license_number' => fake()->unique()->bothify('LIC-####??'),
            'is_active' => true,
        ];
    }
}
