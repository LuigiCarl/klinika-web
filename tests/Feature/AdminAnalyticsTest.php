<?php

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Specialty;
use App\Models\User;
use App\Role;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

it('forbids patients and receptionists from viewing the analytics dashboard', function (Role $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('admin.analytics'))
        ->assertForbidden();
})->with([
    'patient' => Role::Patient,
    'receptionist' => Role::Receptionist,
]);

it('displays clinic KPIs, doctor utilization, and specialty demand for administrators', function () {
    $admin = User::factory()->admin()->create();
    $cardiology = Specialty::factory()->create(['name' => 'Cardiology']);
    $dermatology = Specialty::factory()->create(['name' => 'Dermatology']);

    $doctorA = Doctor::factory()->for($cardiology)->create(['name' => 'Dr. Heart']);
    $doctorB = Doctor::factory()->for($dermatology)->create(['name' => 'Dr. Skin']);
    $patient = Patient::factory()->create();

    $now = CarbonImmutable::now('Asia/Manila');

    Appointment::factory()->create([
        'doctor_id' => $doctorA->id,
        'patient_id' => $patient->id,
        'status' => AppointmentStatus::Completed,
        'created_at' => $now->subHours(24),
        'starts_at' => $now->subHours(12),
        'ends_at' => $now->subHours(11),
    ]);

    Appointment::factory()->create([
        'doctor_id' => $doctorA->id,
        'patient_id' => $patient->id,
        'status' => AppointmentStatus::Confirmed,
        'created_at' => $now->subHours(12),
        'starts_at' => $now->addHours(12),
        'ends_at' => $now->addHours(13),
    ]);

    Appointment::factory()->create([
        'doctor_id' => $doctorB->id,
        'patient_id' => $patient->id,
        'status' => AppointmentStatus::NoShow,
        'created_at' => $now->subHours(48),
        'starts_at' => $now->subHours(24),
        'ends_at' => $now->subHours(23),
    ]);

    Appointment::factory()->create([
        'doctor_id' => $doctorB->id,
        'patient_id' => $patient->id,
        'status' => AppointmentStatus::Cancelled,
        'created_at' => $now->subDays(45),
        'starts_at' => $now->subDays(40),
        'ends_at' => $now->subDays(40)->addHour(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.analytics'))
        ->assertOk()
        ->assertSee('Clinic Analytics & KPIs')
        ->assertSee('Dr. Heart')
        ->assertSee('Dr. Skin')
        ->assertSee('Cardiology')
        ->assertSee('Dermatology');

    Livewire::actingAs($admin)
        ->test('pages::admin.analytics')
        ->assertSet('range', '30')
        ->assertSee('50.0%')
        ->set('range', 'all')
        ->assertSee('33.3%');
});
