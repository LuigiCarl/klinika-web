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

it('filters doctors by available weekday, appointment duration, and quick specialty pills', function () {
    $cardio = Specialty::factory()->create(['name' => 'Cardiology', 'is_active' => true]);
    $neuro = Specialty::factory()->create(['name' => 'Neurology', 'is_active' => true]);

    $mondayDoc = Doctor::factory()->for($cardio)->create([
        'name' => 'Dr. Monday Quick',
        'appointment_duration_minutes' => 15,
        'is_active' => true,
    ]);
    DoctorSchedule::factory()->for($mondayDoc)->create([
        'weekday' => 1,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
    ]);

    $fridayDoc = Doctor::factory()->for($neuro)->create([
        'name' => 'Dr. Friday Deep',
        'appointment_duration_minutes' => 45,
        'is_active' => true,
    ]);
    DoctorSchedule::factory()->for($fridayDoc)->create([
        'weekday' => 5,
        'start_time' => '13:00:00',
        'end_time' => '17:00:00',
    ]);

    Livewire::test('pages::doctors.index')
        ->set('weekday', '1')
        ->assertSee('Dr. Monday Quick')
        ->assertDontSee('Dr. Friday Deep')
        ->set('weekday', '')
        ->set('duration', '45')
        ->assertSee('Dr. Friday Deep')
        ->assertDontSee('Dr. Monday Quick')
        ->call('selectSpecialty', (string) $cardio->id)
        ->assertSet('specialty', (string) $cardio->id)
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('specialty', '')
        ->assertSet('weekday', '')
        ->assertSet('duration', '')
        ->assertSee('Dr. Monday Quick')
        ->assertSee('Dr. Friday Deep');
});

it('displays next available date badge, working weekday chips, and direct book CTA for patients', function () {
    $this->travelTo('2026-10-24 00:00:00');
    $patient = Patient::factory()->create();
    $specialty = Specialty::factory()->create(['name' => 'Orthopedics', 'is_active' => true]);
    $doctor = Doctor::factory()->for($specialty)->create([
        'name' => 'Dr. Ramon Bautista',
        'appointment_duration_minutes' => 30,
        'is_active' => true,
    ]);

    DoctorSchedule::factory()->for($doctor)->create([
        'weekday' => 1,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
    ]);

    Livewire::actingAs($patient->user)
        ->test('pages::doctors.index')
        ->assertSee('Dr. Ramon Bautista')
        ->assertSee('Next available: Oct 26, 2026')
        ->assertSee('Mon')
        ->assertSee('Book now');
});

it('supports quick date selection, morning and afternoon slot grouping, and step navigation guards', function () {
    $this->travelTo('2026-10-24 00:00:00');
    $patient = Patient::factory()->create();
    $specialty = Specialty::factory()->create(['name' => 'Internal Medicine']);
    $doctor = Doctor::factory()->for($specialty)->create([
        'name' => 'Dr. AllDay',
        'appointment_duration_minutes' => 30,
    ]);

    DoctorSchedule::factory()->for($doctor)->create([
        'weekday' => 1,
        'start_time' => '09:00:00',
        'end_time' => '15:00:00',
    ]);

    $morningSlot = CarbonImmutable::parse('2026-11-02 09:00', 'Asia/Manila')->utc();

    Livewire::actingAs($patient->user)
        ->test('pages::patient.book', ['doctor' => $doctor])
        ->assertSee('Morning slots')
        ->assertSee('Afternoon slots')
        ->call('goToStep', 3)
        ->assertSet('step', 1)
        ->call('selectQuickDate', '2026-11-02')
        ->assertSet('date', '2026-11-02')
        ->assertSet('step', 2)
        ->set('selectedSlot', $morningSlot->toIso8601String())
        ->assertSet('step', 3)
        ->assertSee('9:00 AM – 9:30 AM')
        ->call('goToStep', 2)
        ->assertSet('step', 2);
});
