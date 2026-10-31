<?php

use App\AppointmentStatus;
use App\Mail\AppointmentReminder;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Specialty;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;

it('registers appointments:expire and appointments:send-reminders in the scheduler', function () {
    $schedule = app(Schedule::class);
    $commands = collect($schedule->events())->map(fn ($event) => $event->command)->implode(' ');

    expect($commands)->toContain('appointments:expire')
        ->and($commands)->toContain('appointments:send-reminders');
});

it('dispatches email reminders for confirmed appointments within the next 24 hours', function () {
    Mail::fake();

    $specialty = Specialty::factory()->create();
    $doctor = Doctor::factory()->for($specialty)->create();
    $patient = Patient::factory()->create();

    $upcoming = Appointment::factory()->create([
        'doctor_id' => $doctor->id,
        'patient_id' => $patient->id,
        'status' => AppointmentStatus::Confirmed,
        'starts_at' => CarbonImmutable::now('Asia/Manila')->addHours(6)->utc(),
        'ends_at' => CarbonImmutable::now('Asia/Manila')->addHours(7)->utc(),
    ]);

    Appointment::factory()->create([
        'doctor_id' => $doctor->id,
        'patient_id' => $patient->id,
        'status' => AppointmentStatus::Confirmed,
        'starts_at' => CarbonImmutable::now('Asia/Manila')->addDays(5)->utc(),
        'ends_at' => CarbonImmutable::now('Asia/Manila')->addDays(5)->addHour()->utc(),
    ]);

    $this->artisan('appointments:send-reminders')
        ->expectsOutputToContain('Dispatched 1 appointment reminder(s).')
        ->assertSuccessful();

    Mail::assertSent(AppointmentReminder::class, 1);
    Mail::assertSent(AppointmentReminder::class, fn (AppointmentReminder $mail): bool => $mail->appointment->id === $upcoming->id);
});
