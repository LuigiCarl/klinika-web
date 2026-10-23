<?php

namespace App\Models;

use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory;

    protected $fillable = ['first_name', 'last_name', 'date_of_birth', 'mobile', 'email'];

    protected static function booted(): void
    {
        static::saving(function (Patient $patient): void {
            $patient->normalized_email = $patient->email ? Str::lower(trim($patient->email)) : null;
            $patient->normalized_mobile = $patient->mobile ? preg_replace('/\D+/', '', $patient->mobile) : null;
        });
    }

    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }

    /** @return BelongsTo<Clinic, $this> */
    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function getFullNameAttribute(): string
    {
        return $this->first_name.' '.$this->last_name;
    }
}
