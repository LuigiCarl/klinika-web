<?php

namespace Database\Seeders;

use App\Models\ClinicSetting;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Clinic Administrator',
            'email' => 'admin@example.com',
        ]);

        $specialty = Specialty::factory()->create(['name' => 'General Medicine']);
        $doctor = Doctor::factory()->for($specialty)->create([
            'name' => 'Dr. Maria Santos',
            'appointment_duration_minutes' => 30,
        ]);

        foreach (range(1, 5) as $weekday) {
            DoctorSchedule::factory()->for($doctor)->create(['weekday' => $weekday]);
        }

        ClinicSetting::factory()->create();
    }
}
