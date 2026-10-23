<?php

use App\AppointmentStatus;
use App\Models\AdministrativeAudit;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\User;
use App\Services\DoctorScheduleService;
use Illuminate\Validation\ValidationException;

it('rejects overlapping effective working intervals', function () {
    $admin = User::factory()->admin()->create();
    $doctor = Doctor::factory()->create();
    DoctorSchedule::factory()->for($doctor)->create([
        'weekday' => 1,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'effective_from' => '2026-01-01',
    ]);

    expect(fn () => app(DoctorScheduleService::class)->createSchedule($doctor, [
        'weekday' => 1,
        'start_time' => '11:00',
        'end_time' => '13:00',
        'effective_from' => '2026-06-01',
        'effective_until' => null,
    ], $admin))->toThrow(ValidationException::class);
});

it('creates separate intervals for breaks and records an audit', function () {
    $admin = User::factory()->admin()->create();
    $doctor = Doctor::factory()->create();
    $service = app(DoctorScheduleService::class);

    $service->createSchedule($doctor, ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00'], $admin);
    $service->createSchedule($doctor, ['weekday' => 1, 'start_time' => '13:00', 'end_time' => '17:00'], $admin);

    expect($doctor->schedules()->count())->toBe(2)
        ->and(AdministrativeAudit::query()->where('auditable_type', 'DoctorSchedule')->where('action', 'created')->count())->toBe(2);
});

it('rejects unavailability that overlaps an active appointment', function () {
    $admin = User::factory()->admin()->create();
    $doctor = Doctor::factory()->create();
    Appointment::factory()->for($doctor)->create([
        'status' => AppointmentStatus::Confirmed,
        'starts_at' => '2026-11-02 01:00:00',
        'ends_at' => '2026-11-02 01:30:00',
    ]);

    expect(fn () => app(DoctorScheduleService::class)->createUnavailability($doctor, [
        'starts_at' => '2026-11-02 09:00',
        'ends_at' => '2026-11-02 10:00',
        'reason' => 'Training',
    ], $admin))->toThrow(ValidationException::class);
});

it('rejects removing the only interval covering an active appointment', function () {
    $admin = User::factory()->admin()->create();
    $doctor = Doctor::factory()->create();
    $schedule = DoctorSchedule::factory()->for($doctor)->create([
        'weekday' => 1,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
    ]);
    Appointment::factory()->for($doctor)->create([
        'status' => AppointmentStatus::Pending,
        'starts_at' => '2026-11-02 01:00:00',
        'ends_at' => '2026-11-02 01:30:00',
    ]);

    expect(fn () => app(DoctorScheduleService::class)->deleteSchedule($schedule, $admin))
        ->toThrow(ValidationException::class);

    $this->assertModelExists($schedule);
});

it('rejects deactivating a doctor with a future active appointment', function () {
    $admin = User::factory()->admin()->create();
    $doctor = Doctor::factory()->create();
    Appointment::factory()->for($doctor)->create(['status' => AppointmentStatus::Confirmed]);

    expect(fn () => app(DoctorScheduleService::class)->setDoctorActive($doctor, false, $admin))
        ->toThrow(ValidationException::class);

    expect($doctor->refresh()->is_active)->toBeTrue();
});
