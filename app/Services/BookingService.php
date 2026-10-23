<?php

namespace App\Services;

use App\AppointmentStatus;
use App\Exceptions\BookingConflictException;
use App\Models\Appointment;
use App\Models\ClinicSetting;
use App\Models\Doctor;
use App\Models\Patient;
use App\Role;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(private AvailabilityService $availability, private UserActionRateLimiter $rateLimiter) {}

    public function book(Patient $patient, Doctor $doctor, CarbonImmutable $startsAt, string $idempotencyKey): Appointment
    {
        $patient->loadMissing('user');
        $this->assertPatientMayBook($patient);
        $this->rateLimiter->ensureAllowed($patient->user, 'book-appointment', 10);
        $fingerprint = $this->fingerprint($patient, $doctor, $startsAt);

        try {
            return DB::transaction(function () use ($patient, $doctor, $startsAt, $idempotencyKey, $fingerprint): Appointment {
                $lockedDoctor = Doctor::query()->lockForUpdate()->findOrFail($doctor->id);
                $lockedPatient = Patient::query()->with('user')->lockForUpdate()->findOrFail($patient->id);

                $existing = Appointment::query()
                    ->where('patient_id', $lockedPatient->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing !== null) {
                    if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => 'This submission key was already used for a different appointment request.',
                        ]);
                    }

                    return $existing;
                }

                $this->expireStaleAppointments($lockedDoctor, $lockedPatient);
                $lockedDoctor->refresh();
                $this->assertPatientMayBook($lockedPatient);
                $this->availability->assertBookable($lockedDoctor, $lockedPatient, $startsAt);

                $settings = ClinicSetting::query()->lockForUpdate()->findOrFail(1);
                $endsAt = $startsAt->addMinutes($lockedDoctor->appointment_duration_minutes);
                $expiresAt = min(
                    CarbonImmutable::now()->addHours($settings->approval_expiry_hours),
                    $startsAt->subHours($settings->minimum_review_hours),
                );

                $appointment = Appointment::query()->create([
                    'patient_id' => $lockedPatient->id,
                    'doctor_id' => $lockedDoctor->id,
                    'reference_code' => $this->referenceCode(),
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'duration_minutes' => $lockedDoctor->appointment_duration_minutes,
                    'status' => AppointmentStatus::Pending,
                    'expires_at' => $expiresAt,
                    'idempotency_key' => $idempotencyKey,
                    'request_fingerprint' => $fingerprint,
                ]);

                foreach ($this->availability->slotStarts($startsAt, $lockedDoctor->appointment_duration_minutes) as $slotStartsAt) {
                    $appointment->doctorSlotReservations()->create([
                        'doctor_id' => $lockedDoctor->id,
                        'slot_starts_at' => $slotStartsAt,
                    ]);
                    $appointment->patientSlotReservations()->create([
                        'patient_id' => $lockedPatient->id,
                        'slot_starts_at' => $slotStartsAt,
                    ]);
                }

                $appointment->events()->create([
                    'actor_id' => $lockedPatient->user_id,
                    'event_type' => 'requested',
                    'new_status' => AppointmentStatus::Pending,
                ]);

                return $appointment->load(['doctorSlotReservations', 'patientSlotReservations', 'events']);
            }, 3);
        } catch (QueryException $exception) {
            if ($this->isReservationConflict($exception)) {
                throw new BookingConflictException(previous: $exception);
            }

            throw $exception;
        }
    }

    private function assertPatientMayBook(Patient $patient): void
    {
        $user = $patient->user;

        if (! $user->is_active || ! $user->hasRole(Role::Patient)) {
            throw ValidationException::withMessages([
                'patient' => 'An active patient account is required to request an appointment.',
            ]);
        }
    }

    private function expireStaleAppointments(Doctor $doctor, Patient $patient): void
    {
        $appointments = Appointment::query()
            ->where('status', AppointmentStatus::Pending)
            ->where('expires_at', '<=', now())
            ->where(fn ($query) => $query->where('doctor_id', $doctor->id)->orWhere('patient_id', $patient->id))
            ->lockForUpdate()
            ->get();

        foreach ($appointments as $appointment) {
            $appointment->doctorSlotReservations()->delete();
            $appointment->patientSlotReservations()->delete();
            $appointment->update(['status' => AppointmentStatus::Expired]);
            $appointment->events()->create([
                'event_type' => 'expired',
                'old_status' => AppointmentStatus::Pending,
                'new_status' => AppointmentStatus::Expired,
            ]);
        }
    }

    private function fingerprint(Patient $patient, Doctor $doctor, CarbonImmutable $startsAt): string
    {
        return hash('sha256', implode('|', [
            $patient->id,
            $doctor->id,
            $startsAt->utc()->format('Y-m-d\TH:i:s\Z'),
        ]));
    }

    private function referenceCode(): string
    {
        return 'APT-'.Str::upper(Str::random(12));
    }

    private function isReservationConflict(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $message = $exception->getMessage();

        return in_array($sqlState, ['23000', '23505'], true)
            && (str_contains($message, 'doctor_slot_reservations') || str_contains($message, 'patient_slot_reservations'));
    }
}
