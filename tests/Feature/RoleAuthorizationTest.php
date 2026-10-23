<?php

use App\Models\User;
use App\Role;

it('redirects guests from the admin dashboard', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

it('allows admins to visit the admin dashboard', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Administrator dashboard')
        ->assertSee('Manage doctors and schedules')
        ->assertDontSee('placeholder-pattern');
});

it('forbids non-admin roles from the admin dashboard', function (Role $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
})->with(['receptionist' => Role::Receptionist, 'patient' => Role::Patient]);

it('assigns new registrations the patient role and ignores role escalation input', function () {
    $this->post(route('register.store'), [
        'name' => 'New Patient',
        'email' => 'patient@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => Role::Admin->value,
    ])->assertRedirect(route('dashboard', absolute: false));

    expect(User::query()->where('email', 'patient@example.com')->sole()->role)->toBe(Role::Patient);
});
