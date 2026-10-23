<?php

namespace App\Services;

use App\AppointmentStatus;
use App\Models\AdministrativeAudit;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\DoctorUnavailability;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DoctorScheduleService
{
    /** @param array{weekday: int, start_time: string, end_time: string, effective_from?: string|null, effective_until?: string|null} $attributes */
    public function createSchedule(Doctor $doctor, array $attributes, User $actor): DoctorSchedule
    {
        return DB::transaction(function () use ($doctor, $attributes, $actor): DoctorSchedule {
            $lockedDoctor = Doctor::query()->lockForUpdate()->findOrFail($doctor->id);
            $this->assertScheduleDoesNotOverlap($lockedDoctor, $attributes);

            $schedule = $lockedDoctor->schedules()->create($attributes);
            $this->audit($actor, $schedule, 'created', null, $schedule->getAttributes());

            return $schedule;
        });
    }

    public function deleteSchedule(DoctorSchedule $schedule, User $actor): void
    {
        DB::transaction(function () use ($schedule, $actor): void {
            $lockedSchedule = DoctorSchedule::query()->lockForUpdate()->findOrFail($schedule->id);
            $doctor = Doctor::query()->lockForUpdate()->findOrFail($lockedSchedule->doctor_id);

            $this->assertAppointmentsRemainCovered($doctor, $lockedSchedule);
            $before = $lockedSchedule->getAttributes();
            $lockedSchedule->delete();
            $this->audit($actor, $lockedSchedule, 'deleted', $before);
        });
    }

    /** @param array{starts_at: string, ends_at: string, reason: string} $attributes */
    public function createUnavailability(Doctor $doctor, array $attributes, User $actor): DoctorUnavailability
    {
        return DB::transaction(function () use ($doctor, $attributes, $actor): DoctorUnavailability {
            $lockedDoctor = Doctor::query()->lockForUpdate()->findOrFail($doctor->id);
            $startsAt = CarbonImmutable::parse($attributes['starts_at'], 'Asia/Manila')->utc();
            $endsAt = CarbonImmutable::parse($attributes['ends_at'], 'Asia/Manila')->utc();

            $hasConflict = $this->blockingAppointments($lockedDoctor)
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->exists();

            if ($hasConflict) {
                throw ValidationException::withMessages([
                    'unavailability' => 'Resolve affected pending or confirmed appointments before adding this unavailability.',
                ]);
            }

            $unavailability = $lockedDoctor->unavailabilities()->create([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'reason' => $attributes['reason'],
            ]);
            $this->audit($actor, $unavailability, 'created', null, $unavailability->getAttributes());

            return $unavailability;
        });
    }

    public function deleteUnavailability(DoctorUnavailability $unavailability, User $actor): void
    {
        DB::transaction(function () use ($unavailability, $actor): void {
            $locked = DoctorUnavailability::query()->lockForUpdate()->findOrFail($unavailability->id);
            $before = $locked->getAttributes();
            $locked->delete();
            $this->audit($actor, $locked, 'deleted', $before);
        });
    }

    public function setDoctorActive(Doctor $doctor, bool $isActive, User $actor): Doctor
    {
        return DB::transaction(function () use ($doctor, $isActive, $actor): Doctor {
            $lockedDoctor = Doctor::query()->lockForUpdate()->findOrFail($doctor->id);

            if (! $isActive && $this->blockingAppointments($lockedDoctor)->where('starts_at', '>=', now())->exists()) {
                throw ValidationException::withMessages([
                    'doctor' => 'Resolve future pending or confirmed appointments before deactivating this doctor.',
                ]);
            }

            $before = $lockedDoctor->getAttributes();
            $lockedDoctor->update(['is_active' => $isActive]);
            $this->audit($actor, $lockedDoctor, $isActive ? 'activated' : 'deactivated', $before, $lockedDoctor->getAttributes());

            return $lockedDoctor;
        });
    }

    /** @param array{weekday: int, start_time: string, end_time: string, effective_from?: string|null, effective_until?: string|null} $attributes */
    private function assertScheduleDoesNotOverlap(Doctor $doctor, array $attributes): void
    {
        $overlaps = $doctor->schedules()
            ->where('weekday', $attributes['weekday'])
            ->where('start_time', '<', $attributes['end_time'])
            ->where('end_time', '>', $attributes['start_time'])
            ->where(function ($query) use ($attributes): void {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $attributes['effective_from'] ?? '0001-01-01');
            })
            ->where(function ($query) use ($attributes): void {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', $attributes['effective_until'] ?? '9999-12-31');
            })
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['schedule' => 'This working interval overlaps an existing interval.']);
        }
    }

    private function assertAppointmentsRemainCovered(Doctor $doctor, DoctorSchedule $removedSchedule): void
    {
        $remainingSchedules = $doctor->schedules()->whereKeyNot($removedSchedule->id)->get();
        $appointments = $this->blockingAppointments($doctor)->where('starts_at', '>=', now())->get();

        foreach ($appointments as $appointment) {
            $localStart = $appointment->starts_at->setTimezone('Asia/Manila');
            $localEnd = $appointment->ends_at->setTimezone('Asia/Manila');
            $date = $localStart->toDateString();

            $covered = $remainingSchedules->contains(function (DoctorSchedule $schedule) use ($localStart, $localEnd, $date): bool {
                return $schedule->weekday === $localStart->dayOfWeekIso
                    && ($schedule->effective_from === null || $schedule->effective_from->toDateString() <= $date)
                    && ($schedule->effective_until === null || $schedule->effective_until->toDateString() >= $date)
                    && $schedule->start_time <= $localStart->format('H:i:s')
                    && $schedule->end_time >= $localEnd->format('H:i:s');
            });

            if (! $covered) {
                throw ValidationException::withMessages([
                    'schedule' => 'Resolve affected pending or confirmed appointments before removing this interval.',
                ]);
            }
        }
    }

    /** @return Builder<Appointment> */
    private function blockingAppointments(Doctor $doctor): Builder
    {
        return Appointment::query()->whereBelongsTo($doctor)->whereIn('status', [
            AppointmentStatus::Pending,
            AppointmentStatus::Confirmed,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function audit(User $actor, Model $subject, string $action, ?array $before = null, ?array $after = null): void
    {
        AdministrativeAudit::query()->create([
            'actor_id' => $actor->id,
            'auditable_type' => class_basename($subject),
            'auditable_id' => $subject->getKey(),
            'action' => $action,
            'before' => $before,
            'after' => $after,
        ]);
    }
}
