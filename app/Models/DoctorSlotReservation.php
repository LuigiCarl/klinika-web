<?php

namespace App\Models;

use Database\Factories\DoctorSlotReservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorSlotReservation extends Model
{
    /** @use HasFactory<DoctorSlotReservationFactory> */
    use HasFactory;

    protected $fillable = ['appointment_id', 'doctor_id', 'slot_starts_at'];

    protected function casts(): array
    {
        return ['slot_starts_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** @return BelongsTo<Doctor, $this> */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
