<?php

namespace App\Models;

use App\AppointmentStatus;
use Database\Factories\AppointmentEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentEvent extends Model
{
    /** @use HasFactory<AppointmentEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['appointment_id', 'actor_id', 'event_type', 'old_status', 'new_status', 'reason', 'metadata'];

    protected function casts(): array
    {
        return ['old_status' => AppointmentStatus::class, 'new_status' => AppointmentStatus::class, 'metadata' => 'array'];
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
