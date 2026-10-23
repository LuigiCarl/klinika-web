<?php

namespace Database\Seeders;

use App\Models\Practitioner;
use App\Models\ScheduleException;
use App\ScheduleExceptionType;
use Illuminate\Database\Seeder;

class ScheduleExceptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $practitioner = Practitioner::query()->where('license_number', 'PRC-DEMO-001')->sole();

        ScheduleException::factory()->for($practitioner)->create([
            'clinic_id' => $practitioner->clinic_id,
            'type' => ScheduleExceptionType::Unavailable,
            'starts_at' => now()->addWeek()->startOfDay()->addHours(9),
            'ends_at' => now()->addWeek()->startOfDay()->addHours(12),
            'reason' => 'Training',
        ]);
    }
}
