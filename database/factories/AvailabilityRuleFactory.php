<?php

namespace Database\Factories;

use App\Models\AvailabilityRule;
use App\Models\Practitioner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilityRule>
 */
class AvailabilityRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'practitioner_id' => Practitioner::factory(),
            'clinic_id' => fn (array $attributes) => Practitioner::query()->whereKey($attributes['practitioner_id'])->sole()->clinic_id,
            'weekday' => fake()->numberBetween(1, 5),
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'is_active' => true,
        ];
    }
}
