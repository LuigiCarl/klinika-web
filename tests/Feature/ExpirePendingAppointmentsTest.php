<?php

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Services\AppointmentTransitionService;
use Illuminate\Support\Facades\Schedule;

it('expires only due pending requests through the command', function () {
    $due = Appointment::factory()->create(['expires_at' => now()->subMinute()]);
    $future = Appointment::factory()->create(['expires_at' => now()->addMinute()]);
    $confirmed = Appointment::factory()->create(['status' => AppointmentStatus::Confirmed, 'expires_at' => now()->subMinute()]);

    $this->artisan('appointments:expire')->expectsOutput('Expired 1 appointment request(s); 0 failed.')->assertSuccessful();

    expect($due->refresh()->status)->toBe(AppointmentStatus::Expired)
        ->and($future->refresh()->status)->toBe(AppointmentStatus::Pending)
        ->and($confirmed->refresh()->status)->toBe(AppointmentStatus::Confirmed);
});

it('bounds each run and validates the command limit', function () {
    $first = Appointment::factory()->create(['expires_at' => now()->subMinutes(2)]);
    $second = Appointment::factory()->create(['expires_at' => now()->subMinute()]);

    $this->artisan('appointments:expire', ['--limit' => 1])->assertSuccessful();

    expect(Appointment::query()->where('status', AppointmentStatus::Expired)->count())->toBe(1)
        ->and(Appointment::query()->where('status', AppointmentStatus::Pending)->count())->toBe(1);
    $this->artisan('appointments:expire', ['--limit' => 0])->assertExitCode(2);
});

it('continues after one record fails and returns a failure status', function () {
    $first = Appointment::factory()->create(['expires_at' => now()->subMinutes(2)]);
    $second = Appointment::factory()->create(['expires_at' => now()->subMinute()]);
    $service = Mockery::mock(AppointmentTransitionService::class);
    $service->shouldReceive('expireIfOverdue')->once()->with(Mockery::on(fn (Appointment $appointment): bool => $appointment->is($first)))->andThrow(new RuntimeException('Simulated failure'));
    $service->shouldReceive('expireIfOverdue')->once()->with(Mockery::on(fn (Appointment $appointment): bool => $appointment->is($second)))->andReturnTrue();
    app()->instance(AppointmentTransitionService::class, $service);

    $this->artisan('appointments:expire')->expectsOutput('Expired 1 appointment request(s); 1 failed.')->assertFailed();
});

it('schedules expiry every minute with overlap and single-server protection', function () {
    $event = collect(Schedule::events())->first(fn ($event) => str_contains($event->command ?? '', 'appointments:expire'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});
