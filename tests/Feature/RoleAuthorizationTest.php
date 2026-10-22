<?php

use App\Models\User;
use App\Role;

it('redirects guests from the administrator dashboard', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));
});

it('allows administrators to visit the administrator dashboard', function () {
    $administrator = User::factory()->administrator()->create();

    $this->actingAs($administrator)
        ->get(route('admin.dashboard'))
        ->assertOk();
});

it('forbids non-administrators from the administrator dashboard', function (Role $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
})->with([
    'receptionist' => Role::Receptionist,
    'practitioner' => Role::Practitioner,
    'patient' => Role::Patient,
]);

it('assigns new registrations the patient role and ignores role escalation input', function () {
    $this->post(route('register.store'), [
        'name' => 'New Patient',
        'email' => 'patient@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => Role::Administrator->value,
    ])->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'patient@example.com')->sole();

    expect($user->role)->toBe(Role::Patient);
});
