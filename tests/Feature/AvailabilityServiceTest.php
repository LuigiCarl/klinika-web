<?php

use App\Models\Appointment;
use App\Models\ClinicSetting;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\DoctorSlotReservation;
use App\Models\DoctorUnavailability;
use App\Models\Patient;
use App\Models\PatientSlotReservation;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

function availabilityContext(int $duration = 30): array
{
    ClinicSetting::factory()->create();
    $doctor = Doctor::factory()->create(['appointment_duration_minutes' => $duration]);
    $patient = Patient::factory()->create();

    return compact('doctor', 'patient');
}

it('generates 15 minute grid starts that fit the doctor duration', function () {
    $this->travelTo('2026-10-23 00:00:00');
    ['doctor' => $doctor, 'patient' => $patient] = availabilityContext(30);
    DoctorSchedule::factory()->for($doctor)->create([
        'weekday' => 1,
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
    ]);

    $slots = app(AvailabilityService::class)->slots($doctor, CarbonImmutable::parse('2026-11-02', 'Asia/Manila'), $patient);

    expect(array_map(fn (CarbonImmutable $slot): string => $slot->setTimezone('Asia/Manila')->format('H:i'), $slots))
        ->toBe(['09:00', '09:15', '09:30']);
});

it('treats gaps between working intervals as breaks', function () {
    $this->travelTo('2026-10-23 00:00:00');
    ['doctor' => $doctor, 'patient' => $patient] = availabilityContext();
    DoctorSchedule::factory()->for($doctor)->create(['weekday' => 1, 'start_time' => '09:00:00', 'end_time' => '12:00:00']);
    DoctorSchedule::factory()->for($doctor)->create(['weekday' => 1, 'start_time' => '13:00:00', 'end_time' => '17:00:00']);

    $slots = app(AvailabilityService::class)->slots($doctor, CarbonImmutable::parse('2026-11-02', 'Asia/Manila'), $patient);
    $localTimes = array_map(fn (CarbonImmutable $slot): string => $slot->setTimezone('Asia/Manila')->format('H:i'), $slots);

    expect($localTimes)->toContain('11:30', '13:00')
        ->not->toContain('11:45', '12:00', '12:30');
});

it('excludes doctor unavailability and occupied doctor or patient slots', function () {
    $this->travelTo('2026-10-23 00:00:00');
    ['doctor' => $doctor, 'patient' => $patient] = availabilityContext();
    DoctorSchedule::factory()->for($doctor)->create(['weekday' => 1, 'start_time' => '09:00:00', 'end_time' => '11:00:00']);
    DoctorUnavailability::factory()->for($doctor)->create([
        'starts_at' => '2026-11-02 01:30:00',
        'ends_at' => '2026-11-02 02:00:00',
    ]);
    $doctorAppointment = Appointment::factory()->for($doctor)->create(['starts_at' => '2026-11-02 01:00:00', 'ends_at' => '2026-11-02 01:30:00']);
    DoctorSlotReservation::factory()->for($doctorAppointment)->create(['slot_starts_at' => '2026-11-02 01:00:00']);
    $patientAppointment = Appointment::factory()->for($patient)->create(['starts_at' => '2026-11-02 02:00:00', 'ends_at' => '2026-11-02 02:30:00']);
    PatientSlotReservation::factory()->for($patientAppointment)->create(['slot_starts_at' => '2026-11-02 02:00:00']);

    $slots = app(AvailabilityService::class)->slots($doctor, CarbonImmutable::parse('2026-11-02', 'Asia/Manila'), $patient);
    $localTimes = array_map(fn (CarbonImmutable $slot): string => $slot->setTimezone('Asia/Manila')->format('H:i'), $slots);

    expect($localTimes)->not->toContain('09:00', '09:15', '09:30', '09:45', '10:00', '10:15');
});

it('rejects off-grid past lead-time and booking-window starts', function (string $start) {
    $this->travelTo('2026-10-23 00:00:00');
    ['doctor' => $doctor, 'patient' => $patient] = availabilityContext();
    DoctorSchedule::factory()->for($doctor)->create(['weekday' => 5, 'start_time' => '00:00:00', 'end_time' => '23:59:00']);

    expect(fn () => app(AvailabilityService::class)->assertBookable($doctor, $patient, CarbonImmutable::parse($start)))
        ->toThrow(ValidationException::class);
})->with([
    'off grid' => '2026-10-23 05:10:00 UTC',
    'inside lead time' => '2026-10-23 03:00:00 UTC',
    'outside booking window' => '2026-12-01 05:00:00 UTC',
]);
