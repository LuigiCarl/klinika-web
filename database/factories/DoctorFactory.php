<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Specialty;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Doctor> */
class DoctorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'specialty_id' => Specialty::factory(),
            'name' => 'Dr. '.fake()->name(),
            'appointment_duration_minutes' => fake()->randomElement([15, 30, 45, 60]),
            'is_active' => true,
        ];
    }
}
