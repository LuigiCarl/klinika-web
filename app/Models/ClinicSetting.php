<?php

namespace App\Models;

use Database\Factories\ClinicSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClinicSetting extends Model
{
    /** @use HasFactory<ClinicSettingFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $fillable = ['id', 'timezone', 'booking_window_days', 'minimum_lead_hours', 'minimum_review_hours', 'approval_expiry_hours', 'cancellation_cutoff_hours', 'no_show_grace_minutes'];

    protected static function booted(): void
    {
        static::creating(function (ClinicSetting $setting): void {
            $setting->id ??= 1;
        });
    }

    protected function casts(): array
    {
        return [
            'booking_window_days' => 'integer',
            'minimum_lead_hours' => 'integer',
            'minimum_review_hours' => 'integer',
            'approval_expiry_hours' => 'integer',
            'cancellation_cutoff_hours' => 'integer',
            'no_show_grace_minutes' => 'integer',
        ];
    }
}
