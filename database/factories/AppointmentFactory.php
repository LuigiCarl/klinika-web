<?php

namespace Database\Factories;

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
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
            'patient_id' => fn (array $attributes) => Patient::factory()->create(['clinic_id' => $attributes['clinic_id']]),
            'practitioner_id' => fn (array $attributes) => Practitioner::factory()->create(['clinic_id' => $attributes['clinic_id']]),
            'service_id' => fn (array $attributes) => Service::factory()->create(['clinic_id' => $attributes['clinic_id']]),
            'created_by' => User::factory(),
            'status' => AppointmentStatus::Pending,
            'starts_at' => now()->addDay()->startOfHour(),
            'ends_at' => now()->addDay()->startOfHour()->addMinutes(30),
            'idempotency_key' => fake()->uuid(),
        ];
    }
}
