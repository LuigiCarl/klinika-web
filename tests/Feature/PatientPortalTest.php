<?php

use App\Models\Appointment;
use App\Models\ClinicSetting;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Specialty;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(fn () => ClinicSetting::factory()->create());

it('shows only active doctors from active specialties to the public', function () {
    $active = Doctor::factory()->for(Specialty::factory()->create(['name' => 'Cardiology']))->create(['name' => 'Dr. Active']);
    Doctor::factory()->create(['name' => 'Dr. Hidden', 'is_active' => false]);

    $this->get(route('doctors.index'))->assertOk()->assertSee($active->name)->assertDontSee('Dr. Hidden');
});

it('allows an unverified patient and rejects non-patient roles on patient routes', function () {
    $doctor = Doctor::factory()->create();
    $unverified = User::factory()->unverified()->create();
    Patient::factory()->for($unverified)->create();

    $this->actingAs($unverified)->get(route('patient.book', $doctor))->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get(route('patient.dashboard'))->assertForbidden();
});

it('completes the patient booking journey and renders the confirmation', function () {
    $this->travelTo('2026-10-24 00:00:00');
    $patient = Patient::factory()->create();
    $doctor = Doctor::factory()->create(['appointment_duration_minutes' => 30]);
    DoctorSchedule::factory()->for($doctor)->create(['weekday' => 1, 'start_time' => '09:00:00', 'end_time' => '12:00:00']);
    $slot = CarbonImmutable::parse('2026-11-02 09:00', 'Asia/Manila')->utc();

    $this->actingAs($patient->user);
    $component = Livewire::test('pages::patient.book', ['doctor' => $doctor]);
    $component->set('date', '2026-11-02');
    $component->set('selectedSlot', $slot->toIso8601String());
    $component->set('idempotencyKey', (string) Str::uuid());
    $component->call('book')->assertHasNoErrors()->assertRedirect();

    $appointment = Appointment::query()->sole();
    $this->get(route('patient.appointments.confirmation', $appointment))->assertOk()->assertSee('Appointment request received')->assertSee($appointment->reference_code);
});

it('opens the booking form on the next date with an available slot', function () {
    $this->travelTo('2026-10-24 00:00:00');
    $patient = Patient::factory()->create();
    $doctor = Doctor::factory()->create(['appointment_duration_minutes' => 30]);
    DoctorSchedule::factory()->for($doctor)->create([
        'weekday' => 1,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
    ]);

    $component = Livewire::actingAs($patient->user)
        ->test('pages::patient.book', ['doctor' => $doctor]);

    $component->assertSet('date', '2026-10-26');
    expect($component->get('availableSlots'))->not->toBeEmpty();
});

it('does not expose appointment details to another patient', function () {
    $appointment = Appointment::factory()->create();
    $otherPatient = Patient::factory()->create();

    $this->actingAs($otherPatient->user)->get(route('patient.appointments.show', $appointment))->assertForbidden();
});

it('renders responsive patient navigation and appointment history', function () {
    $patient = Patient::factory()->create();
    $appointment = Appointment::factory()->for($patient)->create();

    $this->actingAs($patient->user)->get(route('patient.appointments.index'))->assertOk()
        ->assertSee('My appointments')->assertSee($appointment->reference_code)->assertSee('sm:grid-cols-4', escape: false);
});
