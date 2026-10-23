<?php

use App\Models\ClinicSetting;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\DoctorUnavailability;
use App\Models\Patient;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('connects patients to exactly one user account', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->for($user)->create();

    expect($patient->user->is($user))->toBeTrue()
        ->and($user->patient->is($patient))->toBeTrue();

    expect(fn () => Patient::factory()->for($user)->create())->toThrow(QueryException::class);
});

it('connects doctors to specialties schedules and unavailability', function () {
    $specialty = Specialty::factory()->create();
    $doctor = Doctor::factory()->for($specialty)->create(['appointment_duration_minutes' => 45]);
    $schedule = DoctorSchedule::factory()->for($doctor)->create();
    $unavailability = DoctorUnavailability::factory()->for($doctor)->create();

    expect($doctor->specialty->is($specialty))->toBeTrue()
        ->and($doctor->schedules->sole()->is($schedule))->toBeTrue()
        ->and($doctor->unavailabilities->sole()->is($unavailability))->toBeTrue()
        ->and($doctor->appointment_duration_minutes)->toBe(45);
});

it('enforces doctor and schedule domain constraints', function (Closure $invalidWrite) {
    if (DB::getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite ALTER TABLE cannot apply the production check constraints.');
    }

    expect($invalidWrite)->toThrow(QueryException::class);
})->with([
    'unsupported appointment duration' => fn () => Doctor::factory()->create(['appointment_duration_minutes' => 20]),
    'weekday outside ISO range' => fn () => DoctorSchedule::factory()->create(['weekday' => 8]),
    'schedule end before start' => fn () => DoctorSchedule::factory()->create(['start_time' => '17:00:00', 'end_time' => '09:00:00']),
    'reversed schedule effective dates' => fn () => DoctorSchedule::factory()->create(['effective_from' => '2026-12-31', 'effective_until' => '2026-01-01']),
    'reversed unavailability interval' => fn () => DoctorUnavailability::factory()->create(['starts_at' => '2026-11-02 10:00:00', 'ends_at' => '2026-11-02 09:00:00']),
]);

it('allows only the singleton clinic settings row and applies policy defaults', function () {
    $settings = ClinicSetting::factory()->create();

    expect($settings->id)->toBe(1)
        ->and($settings->timezone)->toBe('Asia/Manila')
        ->and($settings->booking_window_days)->toBe(30)
        ->and($settings->minimum_lead_hours)->toBe(4)
        ->and($settings->minimum_review_hours)->toBe(2)
        ->and($settings->approval_expiry_hours)->toBe(24)
        ->and($settings->cancellation_cutoff_hours)->toBe(2)
        ->and($settings->no_show_grace_minutes)->toBe(15);

    if (DB::getDriverName() !== 'sqlite') {
        expect(fn () => ClinicSetting::factory()->create(['id' => 2]))->toThrow(QueryException::class);
    }
});
