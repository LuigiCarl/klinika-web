<?php

namespace Database\Factories;

use App\Models\ClinicSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClinicSetting> */
class ClinicSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => 1,
            'timezone' => 'Asia/Manila',
            'booking_window_days' => 30,
            'minimum_lead_hours' => 4,
            'minimum_review_hours' => 2,
            'approval_expiry_hours' => 24,
            'cancellation_cutoff_hours' => 2,
            'no_show_grace_minutes' => 15,
        ];
    }
}
