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
 * @property int $clinic_id
 * @property int $patient_id
 * @property int $practitioner_id
 * @property int $service_id
 * @property int $created_by
 * @property AppointmentStatus $status
 * @property CarbonInterface $starts_at
 * @property CarbonInterface $ends_at
 * @property string|null $cancellation_reason
 */
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected $fillable = ['clinic_id', 'patient_id', 'practitioner_id', 'service_id', 'created_by', 'status', 'starts_at', 'ends_at', 'idempotency_key', 'cancellation_reason'];

    protected function casts(): array
    {
        return ['status' => AppointmentStatus::class, 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** @return BelongsTo<Clinic, $this> */
    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Practitioner, $this> */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<AppointmentAudit, $this> */
    public function audits(): HasMany
    {
        return $this->hasMany(AppointmentAudit::class);
    }
}
