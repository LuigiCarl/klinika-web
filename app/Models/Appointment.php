<?php

namespace App\Models;

use App\AppointmentStatus;
use Carbon\CarbonInterface;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $patient_id
 * @property int $doctor_id
 * @property AppointmentStatus $status
 * @property CarbonInterface $starts_at
 * @property CarbonInterface $ends_at
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $approved_at
 * @property CarbonInterface|null $cancelled_at
 * @property CarbonInterface|null $rescheduled_at
 * @property int|null $approved_by
 * @property int|null $cancelled_by
 * @property string|null $cancellation_reason
 */
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected $fillable = ['patient_id', 'doctor_id', 'reference_code', 'starts_at', 'ends_at', 'duration_minutes', 'status', 'expires_at', 'approved_by', 'approved_at', 'cancelled_by', 'cancelled_at', 'rescheduled_at', 'idempotency_key', 'request_fingerprint'];

    protected function casts(): array
    {
        return [
            'status' => AppointmentStatus::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'rescheduled_at' => 'immutable_datetime',
            'duration_minutes' => 'integer',
        ];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Doctor, $this> */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** @return HasMany<AppointmentEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(AppointmentEvent::class);
    }

    /** @return HasMany<DoctorSlotReservation, $this> */
    public function doctorSlotReservations(): HasMany
    {
        return $this->hasMany(DoctorSlotReservation::class);
    }

    /** @return HasMany<PatientSlotReservation, $this> */
    public function patientSlotReservations(): HasMany
    {
        return $this->hasMany(PatientSlotReservation::class);
    }
}
