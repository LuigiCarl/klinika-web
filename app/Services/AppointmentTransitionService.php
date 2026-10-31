<?php

namespace App\Services;

use App\AppointmentStatus;
use App\Mail\AppointmentApproved;
use App\Mail\AppointmentCancelled;
use App\Models\Appointment;
use App\Models\ClinicSetting;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AppointmentTransitionService
{
    public function __construct(private UserActionRateLimiter $rateLimiter) {}

    public function expireIfOverdue(Appointment $appointment): bool
    {
        return DB::transaction(function () use ($appointment): bool {
            Doctor::query()->lockForUpdate()->findOrFail($appointment->doctor_id);
            Patient::query()->lockForUpdate()->findOrFail($appointment->patient_id);
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);

            if ($locked->status !== AppointmentStatus::Pending || $locked->expires_at->isFuture()) {
                return false;
            }

            $this->apply($locked, AppointmentStatus::Expired, null, null);

            return true;
        }, 3);
    }

    public function transitionByReceptionist(Appointment $appointment, AppointmentStatus $target, User $actor, ?string $reason = null): Appointment
    {
        Gate::forUser($actor)->authorize('transition', $appointment);
        $this->rateLimiter->ensureAllowed($actor, 'staff-appointment-transition', 30);

        if (in_array($target, [AppointmentStatus::Rejected, AppointmentStatus::Cancelled], true)) {
            $reason = $this->validatedReason($reason);
        }

        $result = $this->withinLocks($appointment, function (Appointment $locked) use ($target, $actor, $reason): Appointment {
            if ($target === AppointmentStatus::Confirmed && $locked->status === AppointmentStatus::Pending && $locked->expires_at->isPast()) {
                return $this->apply($locked, AppointmentStatus::Expired, null, null);
            }

            $this->assertAllowed($locked, $target);

            if ($target === AppointmentStatus::NoShow) {
                $settings = ClinicSetting::query()->lockForUpdate()->findOrFail(1);
                $eligibleAt = $locked->starts_at->addMinutes($settings->no_show_grace_minutes);

                if (CarbonImmutable::now()->lessThan($eligibleAt)) {
                    throw ValidationException::withMessages(['appointment' => 'The no-show grace period has not elapsed.']);
                }
            }

            return $this->apply($locked, $target, $actor, $reason);
        });

        if ($target === AppointmentStatus::Confirmed && $result->status === AppointmentStatus::Expired) {
            throw ValidationException::withMessages(['appointment' => 'This request expired before it could be approved.']);
        }

        return $result;
    }

    public function cancelByPatient(Appointment $appointment, User $actor): Appointment
    {
        Gate::forUser($actor)->authorize('cancel', $appointment);
        $this->rateLimiter->ensureAllowed($actor, 'patient-appointment-cancellation', 10);

        return $this->withinLocks($appointment, function (Appointment $locked) use ($actor): Appointment {
            if (! in_array($locked->status, [AppointmentStatus::Pending, AppointmentStatus::Confirmed], true)) {
                throw ValidationException::withMessages(['appointment' => 'Only pending or confirmed appointments can be cancelled.']);
            }

            $settings = ClinicSetting::query()->lockForUpdate()->findOrFail(1);

            if (! $locked->starts_at->greaterThan(CarbonImmutable::now()->addHours($settings->cancellation_cutoff_hours))) {
                throw ValidationException::withMessages(['appointment' => 'The self-service cancellation cutoff has passed. Please contact the clinic.']);
            }

            return $this->apply($locked, AppointmentStatus::Cancelled, $actor, null, 'cancelled_by_patient');
        });
    }

    /** @param callable(Appointment): Appointment $callback */
    private function withinLocks(Appointment $appointment, callable $callback): Appointment
    {
        return DB::transaction(function () use ($appointment, $callback): Appointment {
            Doctor::query()->lockForUpdate()->findOrFail($appointment->doctor_id);
            Patient::query()->lockForUpdate()->findOrFail($appointment->patient_id);
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);

            return $callback($locked);
        }, 3);
    }

    private function assertAllowed(Appointment $appointment, AppointmentStatus $target): void
    {
        if (! in_array($target, $appointment->status->allowedTransitions(), true)) {
            throw ValidationException::withMessages([
                'appointment' => "A {$appointment->status->value} appointment cannot become {$target->value}.",
            ]);
        }
    }

    private function apply(Appointment $appointment, AppointmentStatus $target, ?User $actor, ?string $reason, ?string $eventType = null): Appointment
    {
        $oldStatus = $appointment->status;

        if (in_array($target, [AppointmentStatus::Rejected, AppointmentStatus::Expired, AppointmentStatus::Cancelled, AppointmentStatus::NoShow, AppointmentStatus::Completed], true)) {
            $appointment->doctorSlotReservations()->delete();
            $appointment->patientSlotReservations()->delete();
        }

        $attributes = ['status' => $target];

        if ($target === AppointmentStatus::Confirmed) {
            $attributes['approved_by'] = $actor?->id;
            $attributes['approved_at'] = now();
        }

        if ($target === AppointmentStatus::Cancelled) {
            $attributes['cancelled_by'] = $actor?->id;
            $attributes['cancelled_at'] = now();
        }

        $appointment->update($attributes);
        $appointment->events()->create([
            'actor_id' => $actor?->id,
            'event_type' => $eventType ?? $target->value,
            'old_status' => $oldStatus,
            'new_status' => $target,
            'reason' => $reason,
        ]);

        $refreshed = $appointment->refresh()->loadMissing(['doctor.specialty', 'patient.user']);

        DB::afterCommit(function () use ($refreshed, $target, $reason): void {
            $email = $refreshed->patient->user->email;

            if ($target === AppointmentStatus::Confirmed) {
                Mail::to($email)->send(new AppointmentApproved($refreshed));
            } elseif (in_array($target, [AppointmentStatus::Cancelled, AppointmentStatus::Rejected], true)) {
                Mail::to($email)->send(new AppointmentCancelled($refreshed, $reason));
            }
        });

        return $refreshed;
    }

    private function validatedReason(?string $reason): string
    {
        return Validator::make(['reason' => $reason], [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ])->validate()['reason'];
    }
}
