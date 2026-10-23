<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\DoctorSlotReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DoctorSlotReservation> */
class DoctorSlotReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'doctor_id' => fn (array $attributes) => Appointment::query()->whereKey($attributes['appointment_id'])->sole()->doctor_id,
            'slot_starts_at' => fn (array $attributes) => Appointment::query()->whereKey($attributes['appointment_id'])->sole()->starts_at,
        ];
    }
}
