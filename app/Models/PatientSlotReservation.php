<?php

namespace App\Models;

use Database\Factories\PatientSlotReservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientSlotReservation extends Model
{
    /** @use HasFactory<PatientSlotReservationFactory> */
    use HasFactory;

    protected $fillable = ['appointment_id', 'patient_id', 'slot_starts_at'];

    protected function casts(): array
    {
        return ['slot_starts_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
