<?php

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\ClinicSetting;
use App\Models\Patient;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => ClinicSetting::factory()->create());

it('protects receptionist pages from guests patients and admins', function () {
    $this->get(route('receptionist.dashboard'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('receptionist.dashboard'))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route('receptionist.dashboard'))->assertForbidden();
});

it('renders the receptionist dashboard pending queue calendar and search', function () {
    $receptionist = User::factory()->receptionist()->create();
    $appointment = Appointment::factory()->create(['reference_code' => 'APT-SEARCHABLE']);

    $this->actingAs($receptionist)->get(route('receptionist.dashboard'))->assertOk()->assertSee('Receptionist dashboard');
    $this->get(route('receptionist.pending'))->assertOk()->assertSee($appointment->reference_code);
    $this->get(route('receptionist.calendar'))->assertOk()->assertSee('Appointment calendar');
    Livewire::test('pages::receptionist.appointments')->set('search', 'SEARCHABLE')->assertSee($appointment->reference_code);
    Livewire::test('pages::receptionist.calendar')->set('date', 'not-a-date')->assertSee('No appointments on this date.');
});

it('approves a request through the receptionist queue', function () {
    $receptionist = User::factory()->receptionist()->create();
    $appointment = Appointment::factory()->create(['expires_at' => now()->addHour()]);

    $this->actingAs($receptionist);
    Livewire::test('pages::receptionist.pending')->call('approve', $appointment->id)->assertHasNoErrors();

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Confirmed);
});

it('rejects with a reason through appointment details and shows audit history', function () {
    $receptionist = User::factory()->receptionist()->create();
    $appointment = Appointment::factory()->create();

    $this->actingAs($receptionist);
    Livewire::test('pages::receptionist.appointment', ['appointment' => $appointment])
        ->set('rejectionReason', 'Schedule cannot be accommodated.')
        ->call('reject')->assertHasNoErrors()->assertSee('Schedule cannot be accommodated.');

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Rejected);
});

it('escapes patient names in receptionist appointment results', function () {
    $patient = Patient::factory()->for(User::factory()->create(['name' => '<script>alert(1)</script>']))->create();
    $appointment = Appointment::factory()->for($patient)->create();

    $this->actingAs(User::factory()->receptionist()->create())
        ->get(route('receptionist.appointments.index'))
        ->assertOk()->assertSee('&lt;script&gt;', escape: false)->assertDontSee('<script>alert(1)</script>', escape: false);
});
