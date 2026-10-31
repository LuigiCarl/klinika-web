<?php

use App\AppointmentStatus;
use App\Mail\AppointmentApproved;
use App\Models\ClinicSetting;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\DoctorSlotReservation;
use App\Models\Patient;
use App\Models\Specialty;
use App\Models\User;
use App\Services\AppointmentRescheduleService;
use App\Services\AppointmentTransitionService;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('reschedules an appointment, swaps slot reservations atomically, logs audit event, and sends updated invite', function () {
    Mail::fake();

    ClinicSetting::factory()->create();
    $specialty = Specialty::factory()->create();
    $doctor = Doctor::factory()->for($specialty)->create(['appointment_duration_minutes' => 30]);

    $initialStart = CarbonImmutable::now('Asia/Manila')->addDays(3)->setTime(9, 0);
    $newStart = $initialStart->setTime(14, 0);

    DoctorSchedule::factory()->for($doctor)->create([
        'weekday' => $initialStart->dayOfWeekIso,
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
    ]);

    $patient = Patient::factory()->create();
    $receptionist = User::factory()->receptionist()->create();

    $bookingService = app(BookingService::class);
    $transitionService = app(AppointmentTransitionService::class);

    $appointment = $bookingService->book($patient, $doctor, $initialStart->utc(), 'resched-test-1');
    $appointment = $transitionService->transitionByReceptionist($appointment, AppointmentStatus::Confirmed, $receptionist);

    Livewire::actingAs($receptionist)
        ->test('pages::receptionist.appointment', ['appointment' => $appointment])
        ->set('rescheduleStartsAt', $newStart->format('Y-m-d\TH:i'))
        ->set('rescheduleReason', 'Doctor morning emergency')
        ->call('reschedule')
        ->assertHasNoErrors();

    $appointment->refresh();

    expect($appointment->starts_at->equalTo($newStart->utc()))->toBeTrue()
        ->and($appointment->rescheduled_at)->not->toBeNull()
        ->and(DoctorSlotReservation::query()->where('appointment_id', $appointment->id)->where('slot_starts_at', $initialStart->utc())->exists())->toBeFalse()
        ->and(DoctorSlotReservation::query()->where('appointment_id', $appointment->id)->where('slot_starts_at', $newStart->utc())->exists())->toBeTrue()
        ->and($appointment->events()->where('event_type', 'rescheduled')->where('reason', 'Doctor morning emergency')->exists())->toBeTrue();

    Mail::assertSent(AppointmentApproved::class, 2);
});

it('rejects rescheduling into an already booked slot and preserves original reservation', function () {
    Mail::fake();

    ClinicSetting::factory()->create();
    $specialty = Specialty::factory()->create();
    $doctor = Doctor::factory()->for($specialty)->create(['appointment_duration_minutes' => 30]);

    $slotOne = CarbonImmutable::now('Asia/Manila')->addDays(4)->setTime(9, 0);
    $slotTwo = $slotOne->setTime(11, 0);

    DoctorSchedule::factory()->for($doctor)->create([
        'weekday' => $slotOne->dayOfWeekIso,
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
    ]);

    $patientOne = Patient::factory()->create();
    $patientTwo = Patient::factory()->create();
    $receptionist = User::factory()->receptionist()->create();

    $bookingService = app(BookingService::class);
    $rescheduleService = app(AppointmentRescheduleService::class);

    $apptOne = $bookingService->book($patientOne, $doctor, $slotOne->utc(), 'resched-conflict-1');
    $bookingService->book($patientTwo, $doctor, $slotTwo->utc(), 'resched-conflict-2');

    expect(fn () => $rescheduleService->reschedule($apptOne, $slotTwo->utc(), $receptionist, 'Conflict attempt'))
        ->toThrow(ValidationException::class);

    expect($apptOne->refresh()->starts_at->equalTo($slotOne->utc()))->toBeTrue()
        ->and(DoctorSlotReservation::query()->where('appointment_id', $apptOne->id)->where('slot_starts_at', $slotOne->utc())->exists())->toBeTrue();
});

