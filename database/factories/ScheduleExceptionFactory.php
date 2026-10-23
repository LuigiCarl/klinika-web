<?php

namespace Database\Factories;

use App\Models\Practitioner;
use App\Models\ScheduleException;
use App\ScheduleExceptionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleException>
 */
class ScheduleExceptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 day', '+1 month');

        return [
            'practitioner_id' => Practitioner::factory(),
            'clinic_id' => fn (array $attributes) => Practitioner::query()->whereKey($attributes['practitioner_id'])->sole()->clinic_id,
            'type' => ScheduleExceptionType::Unavailable,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+1 hour'),
            'reason' => fake()->sentence(3),
        ];
    }
}
