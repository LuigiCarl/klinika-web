<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;

class AppointmentCancellationService
{
    public function __construct(private AppointmentTransitionService $transitions) {}

    public function cancelByPatient(Appointment $appointment, User $actor): Appointment
    {
        return $this->transitions->cancelByPatient($appointment, $actor);
    }
}
