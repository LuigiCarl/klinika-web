<?php

namespace App\Models;

use App\ScheduleExceptionType;
use Carbon\CarbonInterface;
use Database\Factories\ScheduleExceptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $clinic_id
 * @property int $practitioner_id
 * @property CarbonInterface $starts_at
 * @property CarbonInterface $ends_at
 */
class ScheduleException extends Model
{
    /** @use HasFactory<ScheduleExceptionFactory> */
    use HasFactory;

    protected $fillable = ['clinic_id', 'type', 'starts_at', 'ends_at', 'reason'];

    protected function casts(): array
    {
        return [
            'type' => ScheduleExceptionType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Clinic, $this> */
    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    /** @return BelongsTo<Practitioner, $this> */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }
}
