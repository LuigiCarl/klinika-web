<?php

namespace Database\Seeders;

use App\Models\AvailabilityRule;
use App\Models\Practitioner;
use Illuminate\Database\Seeder;

class AvailabilityRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $practitioner = Practitioner::query()->where('license_number', 'PRC-DEMO-001')->sole();

        foreach (range(1, 5) as $weekday) {
            AvailabilityRule::factory()->for($practitioner)->create([
                'clinic_id' => $practitioner->clinic_id,
                'weekday' => $weekday,
                'starts_at' => '09:00:00',
                'ends_at' => '17:00:00',
            ]);
        }
    }
}
