<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\Practitioner;
use Illuminate\Database\Seeder;

class PractitionerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clinic = Clinic::query()->where('slug', 'klinika-demo-clinic')->sole();
        $practitioner = Practitioner::factory()->for($clinic)->create([
            'display_name' => 'Dr. Maria Santos',
            'license_number' => 'PRC-DEMO-001',
        ]);

        $practitioner->services()->attach(
            $clinic->services()->pluck('id')->all(),
            ['clinic_id' => $clinic->id],
        );
    }
}
