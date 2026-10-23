<?php

use App\Models\AdministrativeAudit;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\DoctorUnavailability;
use App\Models\Specialty;
use App\Models\User;
use App\Role;
use Livewire\Livewire;

it('forbids patients and receptionists from doctor management', function (Role $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route('admin.doctors'))->assertForbidden();
})->with(['patient' => Role::Patient, 'receptionist' => Role::Receptionist]);

it('allows an admin to create specialties doctors and schedules', function () {
    $admin = User::factory()->admin()->create();

    $component = Livewire::actingAs($admin)->test('pages::admin.doctors')
        ->set('specialtyName', 'Family Medicine')
        ->call('createSpecialty')
        ->assertHasNoErrors();

    $specialty = Specialty::query()->sole();
    $component->set('doctorName', 'Dr. Ana Reyes')
        ->set('specialtyId', (string) $specialty->id)
        ->set('durationMinutes', '30')
        ->call('createDoctor')
        ->assertHasNoErrors();

    $doctor = Doctor::query()->sole();
    $component->set('scheduleDoctorId', (string) $doctor->id)
        ->set('weekday', '1')
        ->set('startTime', '09:00')
        ->set('endTime', '12:00')
        ->call('createSchedule')
        ->assertHasNoErrors();

    expect($doctor->schedules()->sole()->start_time)->toStartWith('09:00')
        ->and(AdministrativeAudit::query()->count())->toBe(3);
});

it('validates duration schedule overlap and unavailability intervals', function () {
    $admin = User::factory()->admin()->create();
    $specialty = Specialty::factory()->create();
    $doctor = Doctor::factory()->for($specialty)->create();
    DoctorSchedule::factory()->for($doctor)->create(['weekday' => 1, 'start_time' => '09:00:00', 'end_time' => '12:00:00']);

    Livewire::actingAs($admin)->test('pages::admin.doctors')
        ->set('doctorName', 'Dr. Invalid')
        ->set('specialtyId', (string) $specialty->id)
        ->set('durationMinutes', '20')
        ->call('createDoctor')
        ->assertHasErrors(['durationMinutes'])
        ->set('scheduleDoctorId', (string) $doctor->id)
        ->set('weekday', '1')
        ->set('startTime', '11:00')
        ->set('endTime', '13:00')
        ->call('createSchedule')
        ->assertHasErrors(['schedule'])
        ->set('unavailabilityDoctorId', (string) $doctor->id)
        ->set('unavailableStartsAt', '2026-11-02T10:00')
        ->set('unavailableEndsAt', '2026-11-02T09:00')
        ->set('unavailabilityReason', 'Training')
        ->call('createUnavailability')
        ->assertHasErrors(['unavailableEndsAt']);

    expect(Doctor::query()->count())->toBe(1)
        ->and(DoctorSchedule::query()->count())->toBe(1)
        ->and(DoctorUnavailability::query()->count())->toBe(0);
});

it('updates a doctor profile without changing existing appointment duration', function () {
    $admin = User::factory()->admin()->create();
    $oldSpecialty = Specialty::factory()->create();
    $newSpecialty = Specialty::factory()->create();
    $doctor = Doctor::factory()->for($oldSpecialty)->create(['appointment_duration_minutes' => 30]);
    $appointment = Appointment::factory()->for($doctor)->create(['duration_minutes' => 30]);

    Livewire::actingAs($admin)->test('pages::admin.doctors')
        ->call('editDoctor', $doctor->id)
        ->set('editDoctorName', 'Dr. Updated Name')
        ->set('editSpecialtyId', (string) $newSpecialty->id)
        ->set('editDurationMinutes', '45')
        ->call('updateDoctor')
        ->assertHasNoErrors();

    expect($doctor->refresh()->name)->toBe('Dr. Updated Name')
        ->and($doctor->specialty_id)->toBe($newSpecialty->id)
        ->and($doctor->appointment_duration_minutes)->toBe(45)
        ->and($appointment->refresh()->duration_minutes)->toBe(30)
        ->and(AdministrativeAudit::query()->where('action', 'updated')->exists())->toBeTrue();
});
