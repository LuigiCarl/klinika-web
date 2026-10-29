<?php

use App\AppointmentStatus;
use App\Models\ClinicSetting;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Specialty;
use App\Models\User;
use App\Role;
use App\Services\AppointmentCancellationService;
use App\Services\AppointmentTransitionService;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    ClinicSetting::query()->firstOrCreate(
        ['id' => 1],
        [
            'timezone' => 'Asia/Manila',
            'booking_window_days' => 30,
            'minimum_lead_hours' => 4,
            'minimum_review_hours' => 2,
            'approval_expiry_hours' => 24,
            'cancellation_cutoff_hours' => 2,
            'no_show_grace_minutes' => 15,
        ]
    );
});

it('verifies seeded demonstration accounts and roles', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@example.com')->first();
    $receptionist = User::query()->where('email', 'receptionist@example.com')->first();
    $patient = User::query()->where('email', 'patient@example.com')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->role)->toBe(Role::Admin)
        ->and($receptionist)->not->toBeNull()
        ->and($receptionist->role)->toBe(Role::Receptionist)
        ->and($patient)->not->toBeNull()
        ->and($patient->role)->toBe(Role::Patient)
        ->and($patient->patient)->not->toBeNull();
});

it('completes the full appointment lifecycle: pending -> confirmed -> checked in -> completed', function () {
    $this->travelTo('2026-10-24 00:00:00');

    $patientUser = User::factory()->create();
    $patient = Patient::factory()->for($patientUser)->create();
    $receptionist = User::factory()->receptionist()->create();

    $specialty = Specialty::factory()->create(['name' => 'General QA Medicine']);
    $doctor = Doctor::factory()->for($specialty)->create(['appointment_duration_minutes' => 30]);
    DoctorSchedule::factory()->for($doctor)->create([
        'weekday' => 1,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
    ]);

    $slot = CarbonImmutable::parse('2026-11-02 09:00:00', 'Asia/Manila')->utc();

    // 1. Patient Books
    $bookingService = app(BookingService::class);
    $appointment = $bookingService->book(
        patient: $patient,
        doctor: $doctor,
        startsAt: $slot,
        idempotencyKey: (string) Str::uuid()
    );

    expect($appointment->status)->toBe(AppointmentStatus::Pending)
        ->and($appointment->doctorSlotReservations()->count())->toBe(2); // 30 mins = 2 x 15-min slots

    // 2. Receptionist Approves
    $transitionService = app(AppointmentTransitionService::class);
    $confirmed = $transitionService->transitionByReceptionist($appointment, AppointmentStatus::Confirmed, $receptionist);

    expect($confirmed->status)->toBe(AppointmentStatus::Confirmed)
        ->and($confirmed->doctorSlotReservations()->count())->toBe(2);

    // 3. Receptionist Checks In
    $checkedIn = $transitionService->transitionByReceptionist($confirmed, AppointmentStatus::CheckedIn, $receptionist);
    expect($checkedIn->status)->toBe(AppointmentStatus::CheckedIn);

    // 4. Receptionist Completes Visit
    $completed = $transitionService->transitionByReceptionist($checkedIn, AppointmentStatus::Completed, $receptionist);
    expect($completed->status)->toBe(AppointmentStatus::Completed)
        ->and($completed->events()->count())->toBeGreaterThanOrEqual(4);
});

it('releases reservations upon appointment rejection with reason', function () {
    $this->travelTo('2026-10-24 00:00:00');

    $patient = Patient::factory()->create();
    $receptionist = User::factory()->receptionist()->create();
    $doctor = Doctor::factory()->create(['appointment_duration_minutes' => 30]);
    DoctorSchedule::factory()->for($doctor)->create([
        'weekday' => 1,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
    ]);

    $slot = CarbonImmutable::parse('2026-11-02 09:30:00', 'Asia/Manila')->utc();
    $appointment = app(BookingService::class)->book($patient, $doctor, $slot, (string) Str::uuid());

    expect($appointment->doctorSlotReservations()->count())->toBe(2);

    $rejected = app(AppointmentTransitionService::class)->transitionByReceptionist(
        $appointment,
        AppointmentStatus::Rejected,
        $receptionist,
        'Doctor will be in emergency surgery.'
    );

    expect($rejected->status)->toBe(AppointmentStatus::Rejected)
        ->and($rejected->doctorSlotReservations()->count())->toBe(0)
        ->and($rejected->events()->where('event_type', 'rejected')->exists())->toBeTrue();
});

it('releases reservations when patient cancels prior to cutoff', function () {
    $this->travelTo('2026-10-24 00:00:00');

    $patientUser = User::factory()->create();
    $patient = Patient::factory()->for($patientUser)->create();
    $doctor = Doctor::factory()->create(['appointment_duration_minutes' => 30]);
    DoctorSchedule::factory()->for($doctor)->create([
        'weekday' => 1,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
    ]);

    $slot = CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Manila')->utc();
    $appointment = app(BookingService::class)->book($patient, $doctor, $slot, (string) Str::uuid());

    $cancelled = app(AppointmentCancellationService::class)->cancelByPatient($appointment, $patientUser);

    expect($cancelled->status)->toBe(AppointmentStatus::Cancelled)
        ->and($cancelled->doctorSlotReservations()->count())->toBe(0);
});

it('expires pending appointments past the review window and frees slots', function () {
    $this->travelTo('2026-10-24 00:00:00');

    $patient = Patient::factory()->create();
    $doctor = Doctor::factory()->create(['appointment_duration_minutes' => 30]);
    DoctorSchedule::factory()->for($doctor)->create([
        'weekday' => 1,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
    ]);

    $slot = CarbonImmutable::parse('2026-11-02 10:30:00', 'Asia/Manila')->utc();
    $appointment = app(BookingService::class)->book($patient, $doctor, $slot, (string) Str::uuid());

    // Travel past expiration
    $this->travelTo($appointment->expires_at->addMinute());

    $this->artisan('appointments:expire')->assertSuccessful();

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Expired)
        ->and($appointment->doctorSlotReservations()->count())->toBe(0);
});
