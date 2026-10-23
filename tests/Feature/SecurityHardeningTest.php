<?php

use App\Models\Patient;
use App\Models\User;
use App\Services\UserActionRateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('browser responses include baseline security headers', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
});

test('an inactive account cannot continue using an existing session', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('patient accounts with retained clinic history cannot self delete', function () {
    $patient = Patient::factory()->create();

    $this->actingAs($patient->user);

    Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasErrors('password');

    $this->assertModelExists($patient);
    $this->assertModelExists($patient->user);
});

test('sensitive user actions are rate limited', function () {
    $user = User::factory()->create();
    $action = 'security-test';
    $key = "user-action:{$action}:{$user->id}";
    RateLimiter::clear($key);

    $limiter = app(UserActionRateLimiter::class);
    $limiter->ensureAllowed($user, $action, 2);
    $limiter->ensureAllowed($user, $action, 2);

    try {
        $limiter->ensureAllowed($user, $action, 2);
        $this->fail('Expected the user action rate limiter to reject the request.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('rate_limit');
    } finally {
        RateLimiter::clear($key);
    }
});

test('session cookies use the documented local security baseline', function () {
    expect(config('session.encrypt'))->toBeTrue()
        ->and(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax');
});
