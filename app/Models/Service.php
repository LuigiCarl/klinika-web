<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $clinic_id
 * @property string $name
 * @property int $duration_minutes
 * @property bool $is_active
 */
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected $fillable = ['name', 'duration_minutes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'duration_minutes' => 'integer'];
    }

    /** @return BelongsTo<Clinic, $this> */
    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    /** @return BelongsToMany<Practitioner, $this> */
    public function practitioners(): BelongsToMany
    {
        return $this->belongsToMany(Practitioner::class)
            ->withPivot('clinic_id')
            ->withTimestamps();
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
