<?php

use App\Models\ClinicSetting;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Specialty;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(fn () => ClinicSetting::factory()->create());

it('filters doctors by search query and specialty in the directory', function () {
    $cardio = Specialty::factory()->create(['name' => 'Cardiology', 'is_active' => true]);
    $derma = Specialty::factory()->create(['name' => 'Dermatology', 'is_active' => true]);

    Doctor::factory()->for($cardio)->create(['name' => 'Dr. Maria Santos', 'is_active' => true]);
    Doctor::factory()->for($derma)->create(['name' => 'Dr. Carlos Mendoza', 'is_active' => true]);

    Livewire::test('pages::doctors.index')
        ->assertSee('Dr. Maria Santos')
        ->assertSee('Dr. Carlos Mendoza')
        ->set('search', 'Maria')
        ->assertSee('Dr. Maria Santos')
        ->assertDontSee('Dr. Carlos Mendoza')
        ->call('clearFilters')
        ->assertSee('Dr. Carlos Mendoza')
        ->set('specialty', (string) $derma->id)
        ->assertSee('Dr. Carlos Mendoza')
        ->assertDontSee('Dr. Maria Santos');
});

it('advances the booking stepper through date selection, slot selection, and summary preview', function () {
    $this->travelTo('2026-10-24 00:00:00');
    $patient = Patient::factory()->create();
    $specialty = Specialty::factory()->create(['name' => 'Pediatrics']);
    $doctor = Doctor::factory()->for($specialty)->create(['name' => 'Dr. Stepper', 'appointment_duration_minutes' => 30]);

    DoctorSchedule::factory()->for($doctor)->create([
        'weekday' => 1,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
    ]);

    $slot = CarbonImmutable::parse('2026-11-02 09:00', 'Asia/Manila')->utc();

    Livewire::actingAs($patient->user)
        ->test('pages::patient.book', ['doctor' => $doctor])
        ->assertSet('step', 1)
        ->set('date', '2026-11-02')
        ->assertSet('step', 2)
        ->set('selectedSlot', $slot->toIso8601String())
        ->assertSet('step', 3)
        ->assertSee('Selected Appointment Summary')
        ->assertSee('Dr. Stepper');
});

