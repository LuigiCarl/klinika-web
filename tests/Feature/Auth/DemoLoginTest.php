<?php

use App\Models\Patient;
use App\Models\User;
use App\Role;

it('renders the one-click demo access buttons on the login page', function () {
    $response = $this->get(route('login'));

    $response->assertOk()
        ->assertSee('1-Click Demo Access')
        ->assertSee('Admin')
        ->assertSee('Receptionist')
        ->assertSee('Patient');
});

it('logs in as an admin via one-click demo login', function () {
    $response = $this->post(route('demo.login'), [
        'role' => 'admin',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();

    $user = auth()->user();
    expect($user->email)->toBe('admin@example.com')
        ->and($user->hasRole(Role::Admin))->toBeTrue();
});

it('logs in as a receptionist via one-click demo login', function () {
    $response = $this->post(route('demo.login'), [
        'role' => 'receptionist',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();

    $user = auth()->user();
    expect($user->email)->toBe('receptionist@example.com')
        ->and($user->hasRole(Role::Receptionist))->toBeTrue();
});

it('logs in as a patient via one-click demo login and creates a patient profile', function () {
    $response = $this->post(route('demo.login'), [
        'role' => 'patient',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();

    $user = auth()->user();
    expect($user->email)->toBe('patient@example.com')
        ->and($user->hasRole(Role::Patient))->toBeTrue()
        ->and(Patient::query()->where('user_id', $user->id)->exists())->toBeTrue();
});

it('rejects invalid roles during demo login', function () {
    $response = $this->from(route('login'))->post(route('demo.login'), [
        'role' => 'invalid-role',
    ]);

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors(['role']);

    $this->assertGuest();
});

it('allows an authenticated user to seamlessly switch demo roles', function () {
    $patient = User::factory()->create(['email' => 'patient@example.com']);

    $this->actingAs($patient)
        ->post(route('demo.login'), ['role' => 'receptionist'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
    expect(auth()->user()->hasRole(Role::Receptionist))->toBeTrue();
});
