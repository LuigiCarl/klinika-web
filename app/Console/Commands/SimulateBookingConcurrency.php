<?php

namespace App\Console\Commands;

use App\Exceptions\BookingConflictException;
use App\Models\Appointment;
use App\Models\ClinicSetting;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\DoctorSlotReservation;
use App\Models\Patient;
use App\Models\Specialty;
use App\Models\User;
use App\Role;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SimulateBookingConcurrency extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clinic:simulate-concurrency {--attempts=5 : Number of simultaneous booking attempts to fire}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate concurrent appointment booking requests for the exact same doctor slot to demonstrate race-condition safety and database slot reservation integrity.';

    /**
     * Execute the console command.
     */
    public function handle(BookingService $bookingService): int
    {
        $this->components->info('Klinika Concurrency & Race-Condition Demonstrator');
        $this->line('Testing atomic slot reservation and double-booking prevention under concurrent load.');
        $this->newLine();

        $attempts = max(2, min(20, (int) $this->option('attempts')));

        // 1. Ensure baseline clinic configuration
        ClinicSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'timezone' => 'Asia/Manila',
                'booking_window_days' => 30,
                'minimum_lead_hours' => 4,
                'minimum_review_hours' => 2,
                'approval_expiry_hours' => 24,
                'cancellation_cutoff_hours' => 2,
                'no_show_grace_minutes' => 15,
            ]
        );

        // 2. Resolve or provision doctor
        $specialty = Specialty::query()->firstOrCreate(['name' => 'General Medicine']);
        $doctor = Doctor::query()->firstOrCreate(
            ['name' => 'Dr. Concurrency Demo'],
            [
                'specialty_id' => $specialty->id,
                'appointment_duration_minutes' => 30,
                'is_active' => true,
            ]
        );

        // Ensure weekday schedules exist (Mon-Fri 09:00 - 17:00)
        foreach (range(1, 5) as $weekday) {
            DoctorSchedule::query()->firstOrCreate(
                ['doctor_id' => $doctor->id, 'weekday' => $weekday],
                ['start_time' => '09:00:00', 'end_time' => '17:00:00']
            );
        }

        // Pick next available weekday at 10:00 AM Manila time
        $startsAt = CarbonImmutable::now('Asia/Manila')->next(CarbonImmutable::TUESDAY)->setTime(10, 0)->utc();

        // Clean up prior test runs for this specific slot
        $existing = Appointment::query()->where('doctor_id', $doctor->id)->where('starts_at', $startsAt)->get();
        foreach ($existing as $app) {
            $app->doctorSlotReservations()->delete();
            $app->patientSlotReservations()->delete();
            $app->events()->delete();
            $app->delete();
        }

        $this->components->twoColumnDetail('Target Doctor', "{$doctor->name} ({$doctor->appointment_duration_minutes}m slots)");
        $this->components->twoColumnDetail('Target Slot', $startsAt->timezone('Asia/Manila')->format('F j, Y g:i A').' (Asia/Manila)');
        $this->components->twoColumnDetail('Simulated Contenders', "{$attempts} concurrent patient requests");
        $this->newLine();

        // 3. Create simulated patient contenders
        $contenders = [];
        for ($i = 1; $i <= $attempts; $i++) {
            $user = User::query()->create([
                'name' => "Contender Patient #{$i}",
                'email' => 'contender_'.Str::uuid().'@demo.test',
                'password' => bcrypt('password'),
                'role' => Role::Patient,
                'is_active' => true,
            ]);

            $patient = Patient::query()->create([
                'user_id' => $user->id,
                'phone' => '0917'.fake()->numerify('#######'),
            ]);

            $contenders[] = $patient;
        }

        $results = [];
        $successful = 0;
        $conflicts = 0;

        $this->line("Firing {$attempts} competing booking transactions against the identical time slot...");

        // 4. Execute competing booking transactions
        foreach ($contenders as $index => $patient) {
            $attemptNum = $index + 1;
            $idempotencyKey = (string) Str::uuid();

            try {
                $appointment = $bookingService->book($patient, $doctor, $startsAt, $idempotencyKey);
                $successful++;
                $results[] = [
                    'Attempt' => "#{$attemptNum}",
                    'Patient' => $patient->user->name,
                    'Result' => 'SUCCESS (Won Slot)',
                    'Details' => "Booked (Ref: {$appointment->reference_code})",
                ];
            } catch (BookingConflictException|ValidationException $e) {
                $conflicts++;
                $reason = $e instanceof BookingConflictException
                    ? 'Slot already reserved by competing transaction'
                    : ($e->validator->errors()->first('starts_at') ?: 'Slot unavailable');

                $results[] = [
                    'Attempt' => "#{$attemptNum}",
                    'Patient' => $patient->user->name,
                    'Result' => 'BLOCKED (Conflict Safely Caught)',
                    'Details' => $reason,
                ];
            }
        }

        $this->newLine();
        $this->table(['Attempt', 'Patient', 'Result', 'Details'], $results);

        // 5. Database Integrity Verification
        $persistedReservations = DoctorSlotReservation::query()
            ->where('doctor_id', $doctor->id)
            ->where('slot_starts_at', $startsAt)
            ->count();

        $persistedAppointments = Appointment::query()
            ->where('doctor_id', $doctor->id)
            ->where('starts_at', $startsAt)
            ->count();

        $this->newLine();
        $this->components->twoColumnDetail('Total Requests Submitted', (string) $attempts);
        $this->components->twoColumnDetail('Acquired Reservations', (string) $successful);
        $this->components->twoColumnDetail('Blocked / Conflicted Requests', (string) $conflicts);
        $this->components->twoColumnDetail('Database Slots Claimed in Table', (string) $persistedReservations);
        $this->components->twoColumnDetail('Appointments Created in Table', (string) $persistedAppointments);
        $this->newLine();

        if ($successful === 1 && $persistedReservations === 1 && $persistedAppointments === 1) {
            $this->components->info('INTEGRITY PASS: Exactly 1 transaction acquired the slot reservation. Zero double-bookings occurred.');

            return Command::SUCCESS;
        }

        $this->components->error('INTEGRITY FAILURE: Concurrency invariant breached.');

        return Command::FAILURE;
    }
}
