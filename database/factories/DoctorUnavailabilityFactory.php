<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\DoctorUnavailability;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DoctorUnavailability> */
class DoctorUnavailabilityFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = now()->addWeek()->startOfHour();

        return ['doctor_id' => Doctor::factory(), 'starts_at' => $startsAt, 'ends_at' => $startsAt->copy()->addHour(), 'reason' => 'Unavailable'];
    }
}
