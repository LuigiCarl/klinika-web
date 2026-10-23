<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clinic = Clinic::query()->where('slug', 'klinika-demo-clinic')->sole();

        Service::factory()->for($clinic)->create([
            'name' => 'General Consultation',
            'duration_minutes' => 30,
        ]);

        Service::factory()->for($clinic)->create([
            'name' => 'Follow-up Consultation',
            'duration_minutes' => 15,
        ]);
    }
}
