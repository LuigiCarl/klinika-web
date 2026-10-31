<?php

namespace App\Services;

use App\AppointmentStatus;
use App\Exceptions\BookingConflictException;
use App\Mail\AppointmentApproved;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AppointmentRescheduleService
{
    public function __construct(
        private AvailabilityService $availability,
        private UserActionRateLimiter $rateLimiter,
    ) {}

    public function reschedule(
        Appointment $appointment,
        CarbonImmutable $newStartsAt,
        User $actor,
        ?string $reason = null,
    ): Appointment {
        Gate::forUser($actor)->authorize('transition', $appointment);
        $this->rateLimiter->ensureAllowed($actor, 'staff-appointment-reschedule', 20);

        $validatedReason = Validator::make(['reason' => $reason], [
            'reason' => ['nullable', 'string', 'max:500'],
        ])->validate()['reason'] ?? 'Rescheduled by receptionist';

        try {
            return DB::transaction(function () use ($appointment, $newStartsAt, $actor, $validatedReason): Appointment {
                $lockedDoctor = Doctor::query()->lockForUpdate()->findOrFail($appointment->doctor_id);
                $lockedPatient = Patient::query()->with('user')->lockForUpdate()->findOrFail($appointment->patient_id);
                $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);

                if (! in_array($locked->status, [AppointmentStatus::Pending, AppointmentStatus::Confirmed], true)) {
                    throw ValidationException::withMessages([
                        'appointment' => 'Only pending or confirmed appointments can be rescheduled.',
                    ]);
                }

                $locked->doctorSlotReservations()->delete();
                $locked->patientSlotReservations()->delete();

                $this->availability->assertBookable($lockedDoctor, $lockedPatient, $newStartsAt);

                $endsAt = $newStartsAt->addMinutes($locked->duration_minutes);

                $locked->update([
                    'starts_at' => $newStartsAt,
                    'ends_at' => $endsAt,
                    'rescheduled_at' => now(),
                ]);

                foreach ($this->availability->slotStarts($newStartsAt, $locked->duration_minutes) as $slotStartsAt) {
                    $locked->doctorSlotReservations()->create([
                        'doctor_id' => $lockedDoctor->id,
                        'slot_starts_at' => $slotStartsAt,
                    ]);
                    $locked->patientSlotReservations()->create([
                        'patient_id' => $lockedPatient->id,
                        'slot_starts_at' => $slotStartsAt,
                    ]);
                }

                $locked->events()->create([
                    'actor_id' => $actor->id,
                    'event_type' => 'rescheduled',
                    'old_status' => $locked->status,
                    'new_status' => $locked->status,
                    'reason' => $validatedReason,
                ]);

                $refreshed = $locked->refresh()->loadMissing(['doctor.specialty', 'patient.user', 'events.actor']);

                DB::afterCommit(function () use ($refreshed): void {
                    if ($refreshed->status === AppointmentStatus::Confirmed) {
                        Mail::to($refreshed->patient->user->email)->send(new AppointmentApproved($refreshed));
                    }
                });

                return $refreshed;
            }, 3);
        } catch (QueryException $exception) {
            $sqlState = $exception->errorInfo[0] ?? null;
            $message = $exception->getMessage();

            if (in_array($sqlState, ['23000', '23505'], true)
                && (str_contains($message, 'doctor_slot_reservations') || str_contains($message, 'patient_slot_reservations'))) {
                throw new BookingConflictException(previous: $exception);
            }

            throw $exception;
        }
    }
}
