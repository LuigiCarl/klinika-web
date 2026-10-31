<?php

use App\Models\AdministrativeAudit;
use App\Models\User;
use App\Role;
use Livewire\Livewire;

it('forbids patients and receptionists from managing receptionist staff', function (Role $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('admin.receptionists'))
        ->assertForbidden();
})->with([
    'patient' => Role::Patient,
    'receptionist' => Role::Receptionist,
]);

it('allows an admin to provision, deactivate, and reactivate receptionist accounts with audit logs', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.receptionists')
        ->set('name', 'Rosa Santos')
        ->set('email', 'rosa.reception@example.com')
        ->set('password', 'SecurePass123!')
        ->call('createReceptionist')
        ->assertHasNoErrors();

    $receptionist = User::query()->where('email', 'rosa.reception@example.com')->sole();

    expect($receptionist->role)->toBe(Role::Receptionist)
        ->and($receptionist->is_active)->toBeTrue()
        ->and(AdministrativeAudit::query()->where('action', 'created_receptionist')->exists())->toBeTrue();

    Livewire::actingAs($admin)
        ->test('pages::admin.receptionists')
        ->call('toggleReceptionist', $receptionist->id)
        ->assertHasNoErrors();

    expect($receptionist->refresh()->is_active)->toBeFalse()
        ->and(AdministrativeAudit::query()->where('action', 'deactivated_receptionist')->exists())->toBeTrue();

    Livewire::actingAs($admin)
        ->test('pages::admin.receptionists')
        ->call('toggleReceptionist', $receptionist->id)
        ->assertHasNoErrors();

    expect($receptionist->refresh()->is_active)->toBeTrue()
        ->and(AdministrativeAudit::query()->where('action', 'activated_receptionist')->exists())->toBeTrue();
});
