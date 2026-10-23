<?php

use App\AppointmentStatus;
use App\Exceptions\BookingConflictException;
use App\Models\Appointment;
use App\Models\ClinicSetting;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\DoctorSlotReservation;
use App\Models\Patient;
use App\Models\PatientSlotReservation;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\UserActionRateLimiter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function bookingContext(int $duration = 30): array
{
    ClinicSetting::factory()->create();
    $doctor = Doctor::factory()->create(['appointment_duration_minutes' => $duration]);
    DoctorSchedule::factory()->for($doctor)->create(['weekday' => 1, 'start_time' => '09:00:00', 'end_time' => '17:00:00']);
    $patient = Patient::factory()->create();

    return compact('doctor', 'patient');
}

it('atomically creates a pending request reservations and event', function () {
    $this->travelTo('2026-10-23 00:00:00');
    ['doctor' => $doctor, 'patient' => $patient] = bookingContext(45);

    $appointment = app(BookingService::class)->book(
        $patient,
        $doctor,
        CarbonImmutable::parse('2026-11-02 09:00', 'Asia/Manila')->utc(),
        (string) Str::uuid(),
    );

    expect($appointment->status)->toBe(AppointmentStatus::Pending)
        ->and($appointment->duration_minutes)->toBe(45)
        ->and($appointment->doctorSlotReservations)->toHaveCount(3)
        ->and($appointment->patientSlotReservations)->toHaveCount(3)
        ->and($appointment->events)->toHaveCount(1)
        ->and($appointment->expires_at->toDateTimeString())->toBe('2026-10-24 00:00:00');
});

it('returns the original request for an identical idempotent retry', function () {
    $this->travelTo('2026-10-23 00:00:00');
    ['doctor' => $doctor, 'patient' => $patient] = bookingContext();
    $startsAt = CarbonImmutable::parse('2026-11-02 09:00', 'Asia/Manila')->utc();
    $key = (string) Str::uuid();

    $first = app(BookingService::class)->book($patient, $doctor, $startsAt, $key);
    $second = app(BookingService::class)->book($patient, $doctor, $startsAt, $key);

    expect($second->is($first))->toBeTrue()
        ->and(Appointment::query()->count())->toBe(1)
        ->and(DoctorSlotReservation::query()->count())->toBe(2)
        ->and(PatientSlotReservation::query()->count())->toBe(2);
});

it('rejects an idempotency key reused for a different request', function () {
    $this->travelTo('2026-10-23 00:00:00');
    ['doctor' => $doctor, 'patient' => $patient] = bookingContext();
    $key = (string) Str::uuid();
    app(BookingService::class)->book($patient, $doctor, CarbonImmutable::parse('2026-11-02 09:00', 'Asia/Manila')->utc(), $key);

    expect(fn () => app(BookingService::class)->book($patient, $doctor, CarbonImmutable::parse('2026-11-02 10:00', 'Asia/Manila')->utc(), $key))
        ->toThrow(ValidationException::class);
});

it('blocks doctor and patient overlap without leaving a partial appointment', function () {
    $this->travelTo('2026-10-23 00:00:00');
    ['doctor' => $doctor, 'patient' => $patient] = bookingContext();
    $startsAt = CarbonImmutable::parse('2026-11-02 09:00', 'Asia/Manila')->utc();
    app(BookingService::class)->book($patient, $doctor, $startsAt, (string) Str::uuid());
    $otherPatient = Patient::factory()->create();

    expect(fn () => app(BookingService::class)->book($otherPatient, $doctor, $startsAt, (string) Str::uuid()))
        ->toThrow(ValidationException::class);

    $otherDoctor = Doctor::factory()->create(['appointment_duration_minutes' => 30]);
    DoctorSchedule::factory()->for($otherDoctor)->create(['weekday' => 1, 'start_time' => '09:00:00', 'end_time' => '17:00:00']);
    expect(fn () => app(BookingService::class)->book($patient, $otherDoctor, $startsAt, (string) Str::uuid()))
        ->toThrow(ValidationException::class);

    expect(Appointment::query()->count())->toBe(1);
});

it('rolls back the appointment and doctor reservations when the database rejects a patient slot', function () {
    $this->travelTo('2026-10-23 00:00:00');
    ['doctor' => $doctor, 'patient' => $patient] = bookingContext();
    $startsAt = CarbonImmutable::parse('2026-11-02 09:00', 'Asia/Manila')->utc();
    $existing = Appointment::factory()->for($patient)->create([
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addMinutes(30),
    ]);
    PatientSlotReservation::factory()->for($existing)->create(['slot_starts_at' => $startsAt]);

    $availability = new class extends AvailabilityService
    {
        public function assertBookable(Doctor $doctor, ?Patient $patient, CarbonImmutable $startsAt): void {}
    };

    expect(fn () => (new BookingService($availability, app(UserActionRateLimiter::class)))->book($patient, $doctor, $startsAt, (string) Str::uuid()))
        ->toThrow(BookingConflictException::class);

    expect(Appointment::query()->count())->toBe(1)
        ->and(DoctorSlotReservation::query()->where('doctor_id', $doctor->id)->exists())->toBeFalse()
        ->and(PatientSlotReservation::query()->count())->toBe(1);
});

it('expires stale pending occupancy before booking the same slot', function () {
    $this->travelTo('2026-10-23 00:00:00');
    ['doctor' => $doctor] = bookingContext();
    $oldPatient = Patient::factory()->create();
    $newPatient = Patient::factory()->create();
    $startsAt = CarbonImmutable::parse('2026-11-02 09:00', 'Asia/Manila')->utc();
    $stale = Appointment::factory()->for($doctor)->for($oldPatient)->create([
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addMinutes(30),
        'expires_at' => now()->subMinute(),
    ]);
    foreach ([$startsAt, $startsAt->addMinutes(15)] as $slot) {
        DoctorSlotReservation::factory()->for($stale)->create(['slot_starts_at' => $slot]);
        PatientSlotReservation::factory()->for($stale)->create(['slot_starts_at' => $slot]);
    }

    $created = app(BookingService::class)->book($newPatient, $doctor, $startsAt, (string) Str::uuid());

    expect($stale->refresh()->status)->toBe(AppointmentStatus::Expired)
        ->and($stale->doctorSlotReservations()->exists())->toBeFalse()
        ->and($created->status)->toBe(AppointmentStatus::Pending);
});

it('requires the patient account to be active and assigned the patient role', function () {
    $this->travelTo('2026-10-23 00:00:00');
    ['doctor' => $doctor] = bookingContext();
    $patient = Patient::factory()->for(User::factory()->create(['is_active' => false]))->create();

    expect(fn () => app(BookingService::class)->book(
        $patient,
        $doctor,
        CarbonImmutable::parse('2026-11-02 09:00', 'Asia/Manila')->utc(),
        (string) Str::uuid(),
    ))->toThrow(ValidationException::class);
});
