<?php

use App\Models\AdministrativeAudit;
use App\Models\ClinicSetting;
use App\Models\User;
use App\Role;
use Livewire\Livewire;

it('forbids patients and receptionists from editing clinic settings', function (Role $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('admin.settings'))
        ->assertForbidden();
})->with([
    'patient' => Role::Patient,
    'receptionist' => Role::Receptionist,
]);

it('allows an admin to update clinic operational settings and records an audit entry', function () {
    $admin = User::factory()->admin()->create();
    ClinicSetting::factory()->create([
        'booking_window_days' => 30,
        'no_show_grace_minutes' => 15,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.settings')
        ->assertSet('bookingWindowDays', '30')
        ->set('bookingWindowDays', '45')
        ->set('minimumLeadHours', '6')
        ->set('noShowGraceMinutes', '20')
        ->call('save')
        ->assertHasNoErrors();

    $settings = ClinicSetting::query()->findOrFail(1);

    expect($settings->booking_window_days)->toBe(45)
        ->and($settings->minimum_lead_hours)->toBe(6)
        ->and($settings->no_show_grace_minutes)->toBe(20)
        ->and(AdministrativeAudit::query()->where('action', 'updated_clinic_settings')->exists())->toBeTrue();
});
