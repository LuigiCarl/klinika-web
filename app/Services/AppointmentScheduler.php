<?php

namespace App\Services;

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\User;
use App\ScheduleExceptionType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentScheduler
{
    /** @return list<string> */
    public function availableSlots(Clinic $clinic, Patient $patient, Practitioner $practitioner, Service $service, CarbonImmutable $date): array
    {
        $localDate = $date->setTimezone($clinic->timezone)->startOfDay();
        $ranges = $practitioner->availabilityRules()->where('is_active', true)
            ->where('weekday', $localDate->dayOfWeekIso)
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $localDate->toDateString()))
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', $localDate->toDateString()))
            ->get()->map(fn ($rule): array => [
                CarbonImmutable::parse($localDate->toDateString().' '.$rule->starts_at, $clinic->timezone),
                CarbonImmutable::parse($localDate->toDateString().' '.$rule->ends_at, $clinic->timezone),
            ]);

        $dayStartUtc = $localDate->utc();
        $dayEndUtc = $localDate->addDay()->utc();
        $additionalRanges = $practitioner->scheduleExceptions()->where('type', ScheduleExceptionType::Available->value)
            ->where('starts_at', '<', $dayEndUtc)->where('ends_at', '>', $dayStartUtc)->get()
            ->map(fn ($exception): array => [$exception->starts_at->toImmutable()->setTimezone($clinic->timezone), $exception->ends_at->toImmutable()->setTimezone($clinic->timezone)]);

        $slots = [];
        foreach ($ranges->concat($additionalRanges) as [$rangeStart, $rangeEnd]) {
            for ($slot = $rangeStart; $slot->addMinutes($service->duration_minutes)->lessThanOrEqualTo($rangeEnd); $slot = $slot->addMinutes(15)) {
                try {
                    $this->validateBooking($clinic, $patient, $practitioner, $service, $slot->utc(), $slot->addMinutes($service->duration_minutes)->utc());
                    $slots[] = $slot->format('Y-m-d\TH:i');
                } catch (ValidationException) {
                }
            }
        }

        sort($slots);

        return array_values(array_unique($slots));
    }

    public function create(Clinic $clinic, Patient $patient, Practitioner $practitioner, Service $service, CarbonImmutable $startsAt, User $actor, string $idempotencyKey): Appointment
    {
        return DB::transaction(function () use ($clinic, $patient, $practitioner, $service, $startsAt, $actor, $idempotencyKey): Appointment {
            $existing = $clinic->appointments()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $this->lockResources($practitioner, $patient);
            $endsAt = $startsAt->addMinutes($service->duration_minutes);
            $this->validateBooking($clinic, $patient, $practitioner, $service, $startsAt, $endsAt);

            $appointment = $clinic->appointments()->create([
                'patient_id' => $patient->id,
                'practitioner_id' => $practitioner->id,
                'service_id' => $service->id,
                'created_by' => $actor->id,
                'status' => AppointmentStatus::Pending,
                'starts_at' => $startsAt->utc(),
                'ends_at' => $endsAt->utc(),
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->audit($appointment, $actor, 'created', null, $this->snapshot($appointment));

            return $appointment;
        }, 3);
    }

    public function reschedule(Appointment $appointment, CarbonImmutable $startsAt, User $actor, string $idempotencyKey): Appointment
    {
        return DB::transaction(function () use ($appointment, $startsAt, $actor, $idempotencyKey): Appointment {
            if ($appointment->audits()->where('idempotency_key', $idempotencyKey)->exists()) {
                return $appointment->refresh();
            }

            $appointment->refresh();
            if (! $appointment->status->blocksTime()) {
                throw ValidationException::withMessages(['appointment' => 'Only pending or confirmed appointments may be rescheduled.']);
            }

            $this->lockResources($appointment->practitioner, $appointment->patient);
            if ($appointment->audits()->where('idempotency_key', $idempotencyKey)->exists()) {
                return $appointment->refresh();
            }

            $before = $this->snapshot($appointment);
            $endsAt = $startsAt->addMinutes($appointment->service->duration_minutes);
            $this->validateBooking($appointment->clinic, $appointment->patient, $appointment->practitioner, $appointment->service, $startsAt, $endsAt, $appointment);
            $appointment->update(['starts_at' => $startsAt->utc(), 'ends_at' => $endsAt->utc()]);
            $this->audit($appointment, $actor, 'rescheduled', $before, $this->snapshot($appointment->refresh()), $idempotencyKey);

            return $appointment;
        }, 3);
    }

    public function transition(Appointment $appointment, AppointmentStatus $status, User $actor, ?string $reason = null): Appointment
    {
        return DB::transaction(function () use ($appointment, $status, $actor, $reason): Appointment {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            if (! in_array($status, $appointment->status->allowedTransitions(), true)) {
                throw ValidationException::withMessages(['status' => 'That appointment status transition is not allowed.']);
            }

            $before = $this->snapshot($appointment);
            $appointment->update([
                'status' => $status,
                'cancellation_reason' => $status === AppointmentStatus::Cancelled ? $reason : null,
            ]);
            $this->audit($appointment, $actor, 'status_changed', $before, $this->snapshot($appointment->refresh()));

            return $appointment;
        }, 3);
    }

    private function validateBooking(Clinic $clinic, Patient $patient, Practitioner $practitioner, Service $service, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?Appointment $except = null): void
    {
        if ($patient->clinic_id !== $clinic->id || $practitioner->clinic_id !== $clinic->id || $service->clinic_id !== $clinic->id) {
            throw ValidationException::withMessages(['appointment' => 'All booking records must belong to the clinic.']);
        }

        if (! $clinic->is_active || ! $practitioner->is_active || ! $service->is_active || ! $practitioner->services()->whereKey($service->id)->exists()) {
            throw ValidationException::withMessages(['appointment' => 'The selected practitioner and service are not bookable.']);
        }

        if ($startsAt->minute % 15 !== 0 || $startsAt->second !== 0) {
            throw ValidationException::withMessages(['startsAt' => 'Appointments must start on a 15-minute interval.']);
        }

        if (! $this->isWithinAvailability($clinic, $practitioner, $startsAt, $endsAt)) {
            throw ValidationException::withMessages(['startsAt' => 'The appointment is outside practitioner availability.']);
        }

        $blocking = [AppointmentStatus::Pending->value, AppointmentStatus::Confirmed->value];
        $overlap = static fn ($query) => $query->whereIn('status', $blocking)
            ->where('starts_at', '<', $endsAt->utc())
            ->where('ends_at', '>', $startsAt->utc())
            ->when($except, fn ($query) => $query->whereKeyNot($except->id));

        if ($overlap($clinic->appointments()->where('practitioner_id', $practitioner->id))->exists()) {
            throw ValidationException::withMessages(['startsAt' => 'The practitioner already has an overlapping appointment.']);
        }

        if ($overlap($clinic->appointments()->where('patient_id', $patient->id))->exists()) {
            throw ValidationException::withMessages(['startsAt' => 'The patient already has an overlapping appointment.']);
        }
    }

    private function isWithinAvailability(Clinic $clinic, Practitioner $practitioner, CarbonImmutable $startsAt, CarbonImmutable $endsAt): bool
    {
        $localStart = $startsAt->setTimezone($clinic->timezone);
        $localEnd = $endsAt->setTimezone($clinic->timezone);

        $unavailable = $practitioner->scheduleExceptions()->where('type', ScheduleExceptionType::Unavailable->value)
            ->where('starts_at', '<', $endsAt->utc())->where('ends_at', '>', $startsAt->utc())->exists();
        if ($unavailable) {
            return false;
        }

        $additional = $practitioner->scheduleExceptions()->where('type', ScheduleExceptionType::Available->value)
            ->where('starts_at', '<=', $startsAt->utc())->where('ends_at', '>=', $endsAt->utc())->exists();
        if ($additional) {
            return true;
        }

        if (! $localStart->isSameDay($localEnd)) {
            return false;
        }

        return $practitioner->availabilityRules()->where('is_active', true)
            ->where('weekday', $localStart->dayOfWeekIso)
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $localStart->toDateString()))
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', $localStart->toDateString()))
            ->where('starts_at', '<=', $localStart->format('H:i:s'))
            ->where('ends_at', '>=', $localEnd->format('H:i:s'))
            ->exists();
    }

    private function lockResources(Practitioner $practitioner, Patient $patient): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(?)', [$practitioner->id]);
            DB::select('SELECT pg_advisory_xact_lock(?)', [1_000_000_000 + $patient->id]);
        }

        Practitioner::query()->whereKey($practitioner->id)->lockForUpdate()->firstOrFail();
        Patient::query()->whereKey($patient->id)->lockForUpdate()->firstOrFail();
    }

    /** @return array<string, int|string|null> */
    private function snapshot(Appointment $appointment): array
    {
        return [
            'patient_id' => $appointment->patient_id,
            'practitioner_id' => $appointment->practitioner_id,
            'service_id' => $appointment->service_id,
            'status' => $appointment->status->value,
            'starts_at' => $appointment->starts_at->toISOString(),
            'ends_at' => $appointment->ends_at->toISOString(),
            'cancellation_reason' => $appointment->cancellation_reason,
        ];
    }

    /**
     * @param  array<string, int|string|null>|null  $before
     * @param  array<string, int|string|null>  $after
     */
    private function audit(Appointment $appointment, User $actor, string $action, ?array $before, array $after, ?string $idempotencyKey = null): void
    {
        $appointment->audits()->create(['clinic_id' => $appointment->clinic_id, 'actor_id' => $actor->id, 'action' => $action, 'idempotency_key' => $idempotencyKey, 'before' => $before, 'after' => $after]);
    }
}
