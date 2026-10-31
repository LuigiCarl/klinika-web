<?php

use App\AppointmentStatus;
use App\Models\AdministrativeAudit;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\User;
use App\Role;
use Livewire\Livewire;

it('forbids patients and receptionists from viewing the system audit history', function (Role $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('admin.audits'))
        ->assertForbidden();
})->with([
    'patient' => Role::Patient,
    'receptionist' => Role::Receptionist,
]);

it('displays administrative audits and appointment lifecycle events to administrators', function () {
    $admin = User::factory()->admin()->create(['name' => 'Chief Admin']);
    $appointment = Appointment::factory()->create(['reference_code' => 'APT-AUDIT999999']);

    AdministrativeAudit::query()->create([
        'actor_id' => $admin->id,
        'auditable_type' => 'Doctor',
        'auditable_id' => 42,
        'action' => 'created_doctor_profile',
        'after' => ['name' => 'Dr. Audit Target'],
    ]);

    AppointmentEvent::query()->create([
        'appointment_id' => $appointment->id,
        'actor_id' => $admin->id,
        'event_type' => 'rescheduled',
        'old_status' => AppointmentStatus::Confirmed,
        'new_status' => AppointmentStatus::Confirmed,
        'reason' => 'Doctor conference override',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.audits')
        ->assertSet('tab', 'admin')
        ->assertSee('created doctor profile')
        ->assertSee('Dr. Audit Target')
        ->set('tab', 'appointments')
        ->assertSee('APT-AUDIT999999')
        ->assertSee('Doctor conference override');
});
