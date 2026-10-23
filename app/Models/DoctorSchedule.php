<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\DoctorScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonInterface|null $effective_from
 * @property CarbonInterface|null $effective_until
 */
class DoctorSchedule extends Model
{
    /** @use HasFactory<DoctorScheduleFactory> */
    use HasFactory;

    protected $fillable = ['doctor_id', 'weekday', 'start_time', 'end_time', 'effective_from', 'effective_until'];

    protected function casts(): array
    {
        return ['weekday' => 'integer', 'effective_from' => 'date', 'effective_until' => 'date'];
    }

    /** @return BelongsTo<Doctor, $this> */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
