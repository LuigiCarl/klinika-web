<?php

use App\AppointmentStatus;
use App\Mail\AppointmentApproved;
use App\Mail\AppointmentCancelled;
use App\Mail\AppointmentRequested;
use App\Models\ClinicSetting;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Specialty;
use App\Models\User;
use App\Services\AppointmentTransitionService;
use App\Services\BookingService;
use App\Services\IcsGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

it('dispatches emails on booking request, approval with ics attachment, and cancellation', function () {
    Mail::fake();

    ClinicSetting::factory()->create();
    $specialty = Specialty::factory()->create(['name' => 'General Medicine']);
    $doctor = Doctor::factory()->for($specialty)->create([
        'name' => 'Dr. Elena Cruz',
        'appointment_duration_minutes' => 30,
    ]);

    $startsAt = CarbonImmutable::now('Asia/Manila')->addDays(2)->setTime(10, 0);

    DoctorSchedule::factory()->for($doctor)->create([
        'weekday' => $startsAt->dayOfWeekIso,
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
    ]);

    $patient = Patient::factory()->create();
    $receptionist = User::factory()->receptionist()->create();

    $bookingService = app(BookingService::class);
    $transitionService = app(AppointmentTransitionService::class);

    $appointment = $bookingService->book($patient, $doctor, $startsAt->utc(), 'email-test-key-1');

    Mail::assertSent(AppointmentRequested::class, function (AppointmentRequested $mail) use ($appointment, $patient): bool {
        return $mail->hasTo($patient->user->email)
            && $mail->appointment->id === $appointment->id;
    });

    $approved = $transitionService->transitionByReceptionist($appointment, AppointmentStatus::Confirmed, $receptionist);

    Mail::assertSent(AppointmentApproved::class, function (AppointmentApproved $mail) use ($approved, $patient): bool {
        $attachments = $mail->attachments();

        return $mail->hasTo($patient->user->email)
            && $mail->appointment->id === $approved->id
            && count($attachments) === 1;
    });

    $ics = IcsGenerator::generate($approved);
    expect($ics)->toContain('BEGIN:VCALENDAR')
        ->and($ics)->toContain('BEGIN:VEVENT')
        ->and($ics)->toContain("UID:{$approved->reference_code}@klinika.local")
        ->and($ics)->toContain('Dr. Elena Cruz');

    $transitionService->transitionByReceptionist($approved, AppointmentStatus::Cancelled, $receptionist, 'Doctor emergency leave');

    Mail::assertSent(AppointmentCancelled::class, function (AppointmentCancelled $mail) use ($approved, $patient): bool {
        return $mail->hasTo($patient->user->email)
            && $mail->appointment->id === $approved->id
            && $mail->reason === 'Doctor emergency leave';
    });
});

