<?php

namespace Database\Seeders;

use App\Models\ClinicSetting;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Specialty;
use App\Models\User;
use App\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with demonstration accounts and baseline data.
     */
    public function run(): void
    {
        ClinicSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'timezone' => 'Asia/Manila',
                'booking_window_days' => 30,
                'minimum_lead_hours' => 4,
                'minimum_review_hours' => 2,
                'approval_expiry_hours' => 24,
                'cancellation_cutoff_hours' => 2,
                'no_show_grace_minutes' => 15,
            ]
        );

        // 1. Clinic Administrator
        User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Clinic Administrator',
                'password' => Hash::make('password'),
                'role' => Role::Admin,
                'is_active' => true,
            ]
        );

        // 2. Receptionist Staff
        User::query()->firstOrCreate(
            ['email' => 'receptionist@example.com'],
            [
                'name' => 'Elena Cruz',
                'password' => Hash::make('password'),
                'role' => Role::Receptionist,
                'is_active' => true,
            ]
        );

        // 3. Demonstration Patient
        $patientUser = User::query()->firstOrCreate(
            ['email' => 'patient@example.com'],
            [
                'name' => 'Juan Dela Cruz',
                'password' => Hash::make('password'),
                'role' => Role::Patient,
                'is_active' => true,
            ]
        );

        Patient::query()->firstOrCreate(
            ['user_id' => $patientUser->id],
            ['phone' => '09171234567']
        );

        // 4. Specialties & Doctors
        $med = Specialty::query()->firstOrCreate(['name' => 'General Medicine']);
        $pedia = Specialty::query()->firstOrCreate(['name' => 'Pediatrics']);
        $cardio = Specialty::query()->firstOrCreate(['name' => 'Cardiology']);
        $derma = Specialty::query()->firstOrCreate(['name' => 'Dermatology']);

        $doctors = [
            Doctor::query()->firstOrCreate(
                ['name' => 'Dr. Maria Santos'],
                ['specialty_id' => $med->id, 'appointment_duration_minutes' => 30, 'is_active' => true]
            ),
            Doctor::query()->firstOrCreate(
                ['name' => 'Dr. Jose Rizal'],
                ['specialty_id' => $pedia->id, 'appointment_duration_minutes' => 15, 'is_active' => true]
            ),
            Doctor::query()->firstOrCreate(
                ['name' => 'Dr. Angela Reyes'],
                ['specialty_id' => $cardio->id, 'appointment_duration_minutes' => 45, 'is_active' => true]
            ),
            Doctor::query()->firstOrCreate(
                ['name' => 'Dr. Carlos Mendoza'],
                ['specialty_id' => $derma->id, 'appointment_duration_minutes' => 30, 'is_active' => true]
            ),
        ];

        foreach ($doctors as $doctor) {
            foreach (range(1, 5) as $weekday) {
                DoctorSchedule::query()->firstOrCreate(
                    [
                        'doctor_id' => $doctor->id,
                        'weekday' => $weekday,
                    ],
                    [
                        'start_time' => '09:00:00',
                        'end_time' => '17:00:00',
                    ]
                );
            }
        }
    }
}
