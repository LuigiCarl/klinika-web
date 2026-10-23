<?php

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\DoctorSlotReservation;
use App\Models\PatientSlotReservation;
use App\Services\AppointmentTransitionService;

function overdueAppointmentWithReservations(array $attributes = []): Appointment
{
    $appointment = Appointment::factory()->create([
        'expires_at' => now()->subMinute(),
        ...$attributes,
    ]);

    foreach ([$appointment->starts_at, $appointment->starts_at->addMinutes(15)] as $slot) {
        DoctorSlotReservation::factory()->for($appointment)->create(['doctor_id' => $appointment->doctor_id, 'slot_starts_at' => $slot]);
        PatientSlotReservation::factory()->for($appointment)->create(['patient_id' => $appointment->patient_id, 'slot_starts_at' => $slot]);
    }

    return $appointment;
}

it('atomically expires an overdue pending request and releases reservations', function () {
    $appointment = overdueAppointmentWithReservations();

    $expired = app(AppointmentTransitionService::class)->expireIfOverdue($appointment);

    expect($expired)->toBeTrue()
        ->and($appointment->refresh()->status)->toBe(AppointmentStatus::Expired)
        ->and($appointment->doctorSlotReservations()->exists())->toBeFalse()
        ->and($appointment->patientSlotReservations()->exists())->toBeFalse();
    $this->assertDatabaseHas('appointment_events', [
        'appointment_id' => $appointment->id,
        'actor_id' => null,
        'event_type' => 'expired',
        'old_status' => 'pending',
        'new_status' => 'expired',
    ]);
});

it('is idempotent and rechecks status and deadline under lock', function () {
    $future = Appointment::factory()->create(['expires_at' => now()->addMinute()]);
    $confirmed = Appointment::factory()->create(['status' => AppointmentStatus::Confirmed, 'expires_at' => now()->subMinute()]);
    $service = app(AppointmentTransitionService::class);

    expect($service->expireIfOverdue($future))->toBeFalse()
        ->and($service->expireIfOverdue($confirmed))->toBeFalse();

    $expired = overdueAppointmentWithReservations();
    expect($service->expireIfOverdue($expired))->toBeTrue()
        ->and($service->expireIfOverdue($expired))->toBeFalse()
        ->and(AppointmentEvent::query()->where('appointment_id', $expired->id)->where('event_type', 'expired')->count())->toBe(1);
});
