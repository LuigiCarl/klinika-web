<?php

use App\Models\Appointment;
use App\Models\ClinicSetting;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('allows only one concurrent request to reserve the same doctor slots in PostgreSQL', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL concurrency is verified by the PostgreSQL CI job.');
    }

    expect(function_exists('pcntl_fork'))->toBeTrue('The PostgreSQL runner must provide pcntl.');
    $this->travelTo('2026-10-23 00:00:00');
    ClinicSetting::factory()->create();
    $doctor = Doctor::factory()->create(['appointment_duration_minutes' => 30]);
    DoctorSchedule::factory()->for($doctor)->create(['weekday' => 1, 'start_time' => '09:00:00', 'end_time' => '17:00:00']);
    $patients = Patient::factory()->count(2)->create();
    $startsAt = CarbonImmutable::parse('2026-11-02 09:00', 'Asia/Manila')->utc();
    DB::commit();

    $barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'klinika-booking-'.Str::uuid();
    mkdir($barrier);
    $processIds = [];

    try {
        foreach ($patients as $attempt => $patient) {
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
                    app(BookingService::class)->book(
                        Patient::query()->findOrFail($patient->id),
                        Doctor::query()->findOrFail($doctor->id),
                        $startsAt,
                        (string) Str::uuid(),
                    );
                    file_put_contents($barrier.DIRECTORY_SEPARATOR.'result-'.$attempt, 'created');
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

        $results = [
            file_get_contents($barrier.DIRECTORY_SEPARATOR.'result-0'),
            file_get_contents($barrier.DIRECTORY_SEPARATOR.'result-1'),
        ];

        expect(array_count_values($results)['created'] ?? 0)->toBe(1)
            ->and(Appointment::query()->count())->toBe(1);
    } finally {
        DB::table('appointment_events')->delete();
        DB::table('patient_slot_reservations')->delete();
        DB::table('doctor_slot_reservations')->delete();
        DB::table('appointments')->delete();
        DB::table('doctor_schedules')->delete();
        DB::table('doctor_unavailabilities')->delete();
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
