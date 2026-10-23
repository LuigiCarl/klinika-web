<?php

namespace Database\Factories;

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Appointment> */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = now()->addDays(2)->startOfHour();

        return [
            'patient_id' => Patient::factory(),
            'doctor_id' => Doctor::factory(),
            'reference_code' => 'APT-'.Str::upper(Str::random(12)),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(30),
            'duration_minutes' => 30,
            'status' => AppointmentStatus::Pending,
            'expires_at' => now()->addDay(),
            'idempotency_key' => fake()->uuid(),
            'request_fingerprint' => hash('sha256', fake()->uuid()),
        ];
    }
}
