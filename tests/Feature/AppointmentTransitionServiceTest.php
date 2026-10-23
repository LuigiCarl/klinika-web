<?php

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\ClinicSetting;
use App\Models\DoctorSlotReservation;
use App\Models\PatientSlotReservation;
use App\Models\User;
use App\Services\AppointmentTransitionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => ClinicSetting::factory()->create());

function receptionist(): User
{
    return User::factory()->receptionist()->create();
}

function appointmentWithReservations(array $attributes = []): Appointment
{
    $appointment = Appointment::factory()->create($attributes);
    foreach ([$appointment->starts_at, $appointment->starts_at->addMinutes(15)] as $slot) {
        DoctorSlotReservation::factory()->for($appointment)->create(['doctor_id' => $appointment->doctor_id, 'slot_starts_at' => $slot]);
        PatientSlotReservation::factory()->for($appointment)->create(['patient_id' => $appointment->patient_id, 'slot_starts_at' => $slot]);
    }

    return $appointment;
}

it('approves an unexpired pending request while retaining reservations and auditing the actor', function () {
    $appointment = appointmentWithReservations(['expires_at' => now()->addHour()]);
    $actor = receptionist();

    $result = app(AppointmentTransitionService::class)->transitionByReceptionist($appointment, AppointmentStatus::Confirmed, $actor);

    expect($result->status)->toBe(AppointmentStatus::Confirmed)
        ->and($result->approved_by)->toBe($actor->id)
        ->and($result->approved_at)->not->toBeNull()
        ->and($result->doctorSlotReservations()->count())->toBe(2)
        ->and($result->patientSlotReservations()->count())->toBe(2);
    $this->assertDatabaseHas('appointment_events', ['appointment_id' => $appointment->id, 'actor_id' => $actor->id, 'event_type' => 'confirmed', 'old_status' => 'pending', 'new_status' => 'confirmed']);
});

it('rejects a pending request with a reason and releases reservations', function () {
    $appointment = appointmentWithReservations();

    $result = app(AppointmentTransitionService::class)->transitionByReceptionist($appointment, AppointmentStatus::Rejected, receptionist(), 'Patient requested a different day.');

    expect($result->status)->toBe(AppointmentStatus::Rejected)
        ->and($result->doctorSlotReservations()->exists())->toBeFalse()
        ->and($result->patientSlotReservations()->exists())->toBeFalse();
    $this->assertDatabaseHas('appointment_events', ['appointment_id' => $appointment->id, 'event_type' => 'rejected', 'reason' => 'Patient requested a different day.']);
});

it('expires an overdue request instead of approving it', function () {
    $appointment = appointmentWithReservations(['expires_at' => now()->subMinute()]);

    expect(fn () => app(AppointmentTransitionService::class)->transitionByReceptionist($appointment, AppointmentStatus::Confirmed, receptionist()))
        ->toThrow(ValidationException::class, 'expired before it could be approved');

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Expired)
        ->and($appointment->doctorSlotReservations()->exists())->toBeFalse();
    $this->assertDatabaseHas('appointment_events', ['appointment_id' => $appointment->id, 'event_type' => 'expired']);
});

it('enforces check-in completion and no-show transitions', function () {
    $this->travelTo('2026-10-24 10:30:00');
    $actor = receptionist();
    $confirmed = appointmentWithReservations(['status' => AppointmentStatus::Confirmed, 'starts_at' => now()->subMinutes(30), 'ends_at' => now()]);

    $checkedIn = app(AppointmentTransitionService::class)->transitionByReceptionist($confirmed, AppointmentStatus::CheckedIn, $actor);
    $completed = app(AppointmentTransitionService::class)->transitionByReceptionist($checkedIn, AppointmentStatus::Completed, $actor);

    expect($completed->status)->toBe(AppointmentStatus::Completed)
        ->and($completed->doctorSlotReservations()->exists())->toBeFalse()
        ->and(AppointmentEvent::query()->where('appointment_id', $confirmed->id)->count())->toBe(2);
});

it('prevents no-show before grace and allows it after grace', function () {
    $this->travelTo('2026-10-24 10:00:00');
    $actor = receptionist();
    $appointment = appointmentWithReservations(['status' => AppointmentStatus::Confirmed, 'starts_at' => now(), 'ends_at' => now()->addMinutes(30)]);

    expect(fn () => app(AppointmentTransitionService::class)->transitionByReceptionist($appointment, AppointmentStatus::NoShow, $actor))->toThrow(ValidationException::class);
    $this->travel(16)->minutes();
    $result = app(AppointmentTransitionService::class)->transitionByReceptionist($appointment, AppointmentStatus::NoShow, $actor);

    expect($result->status)->toBe(AppointmentStatus::NoShow)
        ->and($result->doctorSlotReservations()->exists())->toBeFalse();
});

it('requires reasons and rejects invalid or unauthorized transitions', function () {
    $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Pending]);

    expect(fn () => app(AppointmentTransitionService::class)->transitionByReceptionist($appointment, AppointmentStatus::Rejected, receptionist()))->toThrow(ValidationException::class)
        ->and(fn () => app(AppointmentTransitionService::class)->transitionByReceptionist($appointment, AppointmentStatus::Completed, receptionist()))->toThrow(ValidationException::class)
        ->and(fn () => app(AppointmentTransitionService::class)->transitionByReceptionist($appointment, AppointmentStatus::Confirmed, User::factory()->create()))->toThrow(AuthorizationException::class);
});

it('records a staff late cancellation reason and prevents later approval', function () {
    $this->travelTo('2026-10-24 10:00:00');
    $appointment = appointmentWithReservations(['starts_at' => now()->addHour(), 'ends_at' => now()->addMinutes(90)]);
    $service = app(AppointmentTransitionService::class);
    $actor = receptionist();

    $cancelled = $service->transitionByReceptionist($appointment, AppointmentStatus::Cancelled, $actor, 'Patient called after the cutoff.');

    expect($cancelled->status)->toBe(AppointmentStatus::Cancelled)
        ->and($cancelled->cancelled_by)->toBe($actor->id)
        ->and($cancelled->doctorSlotReservations()->exists())->toBeFalse();
    expect(fn () => $service->transitionByReceptionist($appointment, AppointmentStatus::Confirmed, $actor))->toThrow(ValidationException::class);
});
