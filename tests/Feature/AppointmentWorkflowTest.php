<?php

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\Doctor;
use App\Models\DoctorSlotReservation;
use App\Models\Patient;
use App\Models\PatientSlotReservation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('defines only the approved appointment transitions', function () {
    expect(AppointmentStatus::Pending->allowedTransitions())->toBe([
        AppointmentStatus::Confirmed,
        AppointmentStatus::Rejected,
        AppointmentStatus::Expired,
        AppointmentStatus::Cancelled,
    ])->and(AppointmentStatus::Confirmed->allowedTransitions())->toBe([
        AppointmentStatus::CheckedIn,
        AppointmentStatus::Cancelled,
        AppointmentStatus::NoShow,
    ])->and(AppointmentStatus::CheckedIn->allowedTransitions())->toBe([
        AppointmentStatus::Completed,
    ])->and(AppointmentStatus::Completed->allowedTransitions())->toBe([]);
});

it('connects an appointment to its domain records and append-only events', function () {
    $appointment = Appointment::factory()->create();
    $event = AppointmentEvent::factory()->for($appointment)->create();

    expect($appointment->patient)->toBeInstanceOf(Patient::class)
        ->and($appointment->doctor)->toBeInstanceOf(Doctor::class)
        ->and($appointment->events->sole()->is($event))->toBeTrue()
        ->and($event->updated_at)->toBeNull();
});

it('enforces appointment interval duration status and idempotency constraints', function (Closure $invalidWrite) {
    if (DB::getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite ALTER TABLE cannot apply the production check constraints.');
    }

    expect($invalidWrite)->toThrow(QueryException::class);
})->with([
    'end before start' => fn () => Appointment::factory()->create(['starts_at' => '2026-11-02 10:00:00', 'ends_at' => '2026-11-02 09:00:00']),
    'unsupported duration' => fn () => Appointment::factory()->create(['duration_minutes' => 20]),
    'duplicate patient submission key' => function () {
        $first = Appointment::factory()->create();

        Appointment::factory()->for($first->patient)->create(['idempotency_key' => $first->idempotency_key]);
    },
]);

it('rejects appointment statuses outside the approved state machine', function () {
    if (DB::getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite ALTER TABLE cannot apply the production check constraints.');
    }

    $appointment = Appointment::factory()->create();

    expect(fn () => DB::table('appointments')->where('id', $appointment->id)->update(['status' => 'rescheduled']))
        ->toThrow(QueryException::class);
});

it('prevents duplicate doctor and patient reservations for a 15 minute slot', function () {
    $appointment = Appointment::factory()->create();
    DoctorSlotReservation::factory()->for($appointment)->create();
    PatientSlotReservation::factory()->for($appointment)->create();

    expect(fn () => DoctorSlotReservation::factory()->for($appointment)->create())->toThrow(QueryException::class);
    expect(fn () => PatientSlotReservation::factory()->for($appointment)->create())->toThrow(QueryException::class);
});

it('rejects reservations whose resource does not belong to the appointment', function () {
    $appointment = Appointment::factory()->create();

    expect(fn () => DoctorSlotReservation::factory()->for($appointment)->create([
        'doctor_id' => Doctor::factory()->create()->id,
    ]))->toThrow(QueryException::class);
    expect(fn () => PatientSlotReservation::factory()->for($appointment)->create([
        'patient_id' => Patient::factory()->create()->id,
    ]))->toThrow(QueryException::class);
});
