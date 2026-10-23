<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\PatientSlotReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PatientSlotReservation> */
class PatientSlotReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'patient_id' => fn (array $attributes) => Appointment::query()->whereKey($attributes['appointment_id'])->sole()->patient_id,
            'slot_starts_at' => fn (array $attributes) => Appointment::query()->whereKey($attributes['appointment_id'])->sole()->starts_at,
        ];
    }
}
