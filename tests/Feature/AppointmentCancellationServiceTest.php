<?php

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\ClinicSetting;
use App\Models\DoctorSlotReservation;
use App\Models\Patient;
use App\Models\PatientSlotReservation;
use App\Services\AppointmentCancellationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => ClinicSetting::factory()->create(['cancellation_cutoff_hours' => 2]));

it('lets the owning patient cancel before cutoff and atomically releases reservations', function () {
    $this->travelTo('2026-10-24 08:00:00');
    $patient = Patient::factory()->create();
    $appointment = Appointment::factory()->for($patient)->create(['status' => AppointmentStatus::Confirmed, 'starts_at' => now()->addHours(4), 'ends_at' => now()->addHours(4)->addMinutes(30)]);
    DoctorSlotReservation::factory()->for($appointment)->create(['doctor_id' => $appointment->doctor_id, 'slot_starts_at' => $appointment->starts_at]);
    PatientSlotReservation::factory()->for($appointment)->create(['patient_id' => $patient->id, 'slot_starts_at' => $appointment->starts_at]);

    app(AppointmentCancellationService::class)->cancelByPatient($appointment, $patient->user);

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Cancelled)
        ->and($appointment->cancelled_by)->toBe($patient->user_id)
        ->and($appointment->doctorSlotReservations()->exists())->toBeFalse()
        ->and($appointment->patientSlotReservations()->exists())->toBeFalse()
        ->and(AppointmentEvent::query()->where('appointment_id', $appointment->id)->where('event_type', 'cancelled_by_patient')->exists())->toBeTrue();
});

it('rejects self-service cancellation at or after cutoff', function () {
    $this->travelTo('2026-10-24 08:00:00');
    $patient = Patient::factory()->create();
    $appointment = Appointment::factory()->for($patient)->create(['starts_at' => now()->addHours(2), 'ends_at' => now()->addHours(2)->addMinutes(30)]);

    expect(fn () => app(AppointmentCancellationService::class)->cancelByPatient($appointment, $patient->user))->toThrow(ValidationException::class);
    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Pending);
});

it('prevents a patient from cancelling another patients appointment', function () {
    $appointment = Appointment::factory()->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addMinutes(30)]);
    $otherPatient = Patient::factory()->create();

    expect(fn () => app(AppointmentCancellationService::class)->cancelByPatient($appointment, $otherPatient->user))->toThrow(AuthorizationException::class);
});
