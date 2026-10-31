<?php

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\ClinicSetting;
use App\Models\DoctorSlotReservation;
use App\Models\PatientSlotReservation;
use App\Models\User;
use App\Services\AppointmentCancellationService;
use App\Services\AppointmentTransitionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('serializes simultaneous approval and patient cancellation in PostgreSQL', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL transition concurrency is verified by the PostgreSQL CI job.');
    }

    expect(function_exists('pcntl_fork'))->toBeTrue('The PostgreSQL runner must provide pcntl.');
    ClinicSetting::factory()->create();
    $appointment = Appointment::factory()->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addMinutes(30)]);
    $receptionist = User::factory()->receptionist()->create();

    foreach ([$appointment->starts_at, $appointment->starts_at->addMinutes(15)] as $slot) {
        DoctorSlotReservation::factory()->for($appointment)->create(['doctor_id' => $appointment->doctor_id, 'slot_starts_at' => $slot]);
        PatientSlotReservation::factory()->for($appointment)->create(['patient_id' => $appointment->patient_id, 'slot_starts_at' => $slot]);
    }

    DB::commit();
    $barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'klinika-transition-'.Str::uuid();
    mkdir($barrier);
    $processIds = [];

    try {
        foreach (['approve', 'cancel'] as $attempt => $action) {
            $processId = pcntl_fork();
            expect($processId)->toBeGreaterThanOrEqual(0);

            if ($processId === 0) {
                DB::disconnect();
                DB::reconnect();
                touch($barrier.DIRECTORY_SEPARATOR.'ready-'.$attempt);
                while (! file_exists($barrier.DIRECTORY_SEPARATOR.'go')) {
                    usleep(1000);
                }

                try {
                    $freshAppointment = Appointment::query()->findOrFail($appointment->id);
                    if ($action === 'approve') {
                        app(AppointmentTransitionService::class)->transitionByReceptionist($freshAppointment, AppointmentStatus::Confirmed, User::query()->findOrFail($receptionist->id));
                    } else {
                        app(AppointmentCancellationService::class)->cancelByPatient($freshAppointment, $freshAppointment->patient->user);
                    }
                    file_put_contents($barrier.DIRECTORY_SEPARATOR.'result-'.$attempt, 'completed');
                } catch (Throwable $exception) {
                    file_put_contents($barrier.DIRECTORY_SEPARATOR.'result-'.$attempt, $exception::class);
                }

                if (function_exists('posix__exit')) {
                    posix__exit(0);
                }

                exit(0);
            }

            $processIds[] = $processId;
        }

        $deadline = microtime(true) + 10;
        while ((! file_exists($barrier.DIRECTORY_SEPARATOR.'ready-0') || ! file_exists($barrier.DIRECTORY_SEPARATOR.'ready-1')) && microtime(true) < $deadline) {
            usleep(1000);
        }
        touch($barrier.DIRECTORY_SEPARATOR.'go');

        foreach ($processIds as $processId) {
            pcntl_waitpid($processId, $status);
        }

        expect(file_get_contents($barrier.DIRECTORY_SEPARATOR.'result-1'))->toBe('completed')
            ->and(Appointment::query()->findOrFail($appointment->id)->status)->toBe(AppointmentStatus::Cancelled)
            ->and(DoctorSlotReservation::query()->where('appointment_id', $appointment->id)->exists())->toBeFalse()
            ->and(PatientSlotReservation::query()->where('appointment_id', $appointment->id)->exists())->toBeFalse();
    } finally {
        DB::table('appointment_events')->delete();
        DB::table('patient_slot_reservations')->delete();
        DB::table('doctor_slot_reservations')->delete();
        DB::table('appointments')->delete();
        DB::table('doctors')->delete();
        DB::table('specialties')->delete();
        DB::table('patients')->delete();
        DB::table('clinic_settings')->delete();
        DB::table('users')->delete();

        foreach (glob($barrier.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($barrier);
        DB::beginTransaction();
    }
});
