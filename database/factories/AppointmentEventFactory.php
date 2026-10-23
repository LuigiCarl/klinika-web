<?php

namespace Database\Factories;

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AppointmentEvent> */
class AppointmentEventFactory extends Factory
{
    public function definition(): array
    {
        return ['appointment_id' => Appointment::factory(), 'event_type' => 'requested', 'new_status' => AppointmentStatus::Pending];
    }
}
