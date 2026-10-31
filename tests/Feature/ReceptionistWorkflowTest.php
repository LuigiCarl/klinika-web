<?php

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\ClinicSetting;
use App\Models\Doctor;
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
    Livewire::test('pages::receptionist.calendar')->set('date', 'not-a-date')->assertSee('No appointments scheduled for this date and filter.');
});

it('filters the calendar manifest by doctor and exports CSV', function () {
    $receptionist = User::factory()->receptionist()->create();
    $this->actingAs($receptionist);

    $docA = Doctor::factory()->create(['name' => 'Dr. Alpha']);
    $docB = Doctor::factory()->create(['name' => 'Dr. Beta']);

    $today = now('Asia/Manila')->startOfDay();
    $aptA = Appointment::factory()->for($docA)->create(['starts_at' => $today->clone()->addHours(10)->utc(), 'reference_code' => 'APT-ALPHA']);
    $aptB = Appointment::factory()->for($docB)->create(['starts_at' => $today->clone()->addHours(11)->utc(), 'reference_code' => 'APT-BETA']);

    $component = Livewire::test('pages::receptionist.calendar')
        ->set('date', $today->toDateString())
        ->assertSee('APT-ALPHA')
        ->assertSee('APT-BETA');

    // Filter by Doctor A
    $component->set('doctorId', (string) $docA->id)
        ->assertSee('APT-ALPHA')
        ->assertDontSee('APT-BETA');

    // Export CSV using Livewire download assertion
    $component->call('exportCsv')
        ->assertFileDownloaded("clinic-manifest-{$today->toDateString()}.csv");
});

it('approves a request through the receptionist queue', function () {
    $receptionist = User::factory()->receptionist()->create();
    $appointment = Appointment::factory()->create(['expires_at' => now()->addHour()]);

    $this->actingAs($receptionist);
    Livewire::test('pages::receptionist.pending')->call('approve', $appointment->id)->assertHasNoErrors();

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Confirmed);
});

it('polls and alerts when new pending requests arrive in the triage queue', function () {
    $receptionist = User::factory()->receptionist()->create();
    $this->actingAs($receptionist);

    $component = Livewire::test('pages::receptionist.pending');
    expect($component->get('previousCount'))->toBe(0);

    // Simulate new patient booking an appointment
    $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Pending]);

    $component->call('checkQueue')
        ->assertDispatched('new-request-alert', count: 1);

    expect($component->get('previousCount'))->toBe(1);
});

it('allows receptionist to toggle sound alert notification preference', function () {
    $receptionist = User::factory()->receptionist()->create();
    $this->actingAs($receptionist);

    Livewire::test('pages::receptionist.pending')
        ->assertSet('soundEnabled', true)
        ->set('soundEnabled', false)
        ->assertSet('soundEnabled', false);
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
