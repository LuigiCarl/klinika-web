<?php

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\User;
use App\Role;
use App\Services\AppointmentScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function appointmentContext(Role $membershipRole = Role::Receptionist): array
{
    $user = User::factory()->create(['role' => $membershipRole]);
    $clinic = Clinic::factory()->create(['timezone' => 'Asia/Manila']);
    $clinic->users()->attach($user, ['role' => $membershipRole->value]);
    $patient = Patient::factory()->for($clinic)->create();
    $practitioner = Practitioner::factory()->for($clinic)->create();
    $service = Service::factory()->for($clinic)->create(['duration_minutes' => 30]);
    $practitioner->services()->attach($service, ['clinic_id' => $clinic->id]);
    $practitioner->availabilityRules()->create([
        'clinic_id' => $clinic->id, 'weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00', 'is_active' => true,
    ]);

    return compact('user', 'clinic', 'patient', 'practitioner', 'service');
}

it('allows clinic administrators and receptionists to open appointment management', function (Role $role) {
    ['user' => $user, 'clinic' => $clinic] = appointmentContext($role);

    $this->actingAs($user)->get(route('clinics.appointments', $clinic))->assertOk();
})->with([Role::Administrator, Role::Receptionist]);

it('hides appointment management from unauthorized and cross-clinic users', function () {
    ['clinic' => $clinic] = appointmentContext();
    $outsider = User::factory()->practitioner()->create();

    $this->actingAs($outsider)->get(route('clinics.appointments', $clinic))->assertNotFound();
});

it('lets practitioners view only their own clinic schedule', function () {
    ['user' => $staff, 'clinic' => $clinic, 'patient' => $patient, 'practitioner' => $practitioner, 'service' => $service] = appointmentContext();
    $practitionerUser = User::factory()->practitioner()->create();
    $clinic->users()->attach($practitionerUser, ['role' => Role::Practitioner->value]);
    $practitioner->forceFill(['user_id' => $practitionerUser->id])->save();
    $scheduler = app(AppointmentScheduler::class);
    $scheduler->create($clinic, $patient, $practitioner, $service, CarbonImmutable::parse('2026-11-02 09:00', $clinic->timezone)->utc(), $staff, fake()->uuid());
    $otherPractitioner = Practitioner::factory()->for($clinic)->create();
    $otherService = Service::factory()->for($clinic)->create(['duration_minutes' => 30]);
    $otherPractitioner->services()->attach($otherService, ['clinic_id' => $clinic->id]);
    $otherPractitioner->availabilityRules()->create(['clinic_id' => $clinic->id, 'weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00', 'is_active' => true]);
    $otherPatient = Patient::factory()->for($clinic)->create(['first_name' => 'Hidden', 'last_name' => 'Patient']);
    $scheduler->create($clinic, $otherPatient, $otherPractitioner, $otherService, CarbonImmutable::parse('2026-11-02 10:00', $clinic->timezone)->utc(), $staff, fake()->uuid());

    Livewire::actingAs($practitionerUser)->test('pages::clinics.appointments', ['clinic' => $clinic])
        ->assertSee($patient->full_name)->assertDontSee('Hidden Patient')
        ->assertDontSee('Reschedule')->assertDontSee('Audit history');
});

it('shows appointment audit history only to clinic administrators', function () {
    ['user' => $receptionist, 'clinic' => $clinic, 'patient' => $patient, 'practitioner' => $practitioner, 'service' => $service] = appointmentContext();
    $administrator = User::factory()->administrator()->create();
    $clinic->users()->attach($administrator, ['role' => Role::Administrator->value]);
    app(AppointmentScheduler::class)->create($clinic, $patient, $practitioner, $service, CarbonImmutable::parse('2026-11-02 09:00', $clinic->timezone)->utc(), $receptionist, fake()->uuid());

    Livewire::actingAs($receptionist)->test('pages::clinics.appointments', ['clinic' => $clinic])->assertDontSee('Audit history');
    Livewire::actingAs($administrator)->test('pages::clinics.appointments', ['clinic' => $clinic])->assertSee('Audit history');
});

it('warns before creating a patient with matching contact details', function () {
    ['user' => $user, 'clinic' => $clinic, 'patient' => $patient] = appointmentContext();

    $component = Livewire::actingAs($user)->test('pages::clinics.appointments', ['clinic' => $clinic])
        ->set('firstName', 'Duplicate')->set('lastName', 'Patient')->set('mobile', $patient->mobile)
        ->call('createPatient')->assertHasErrors(['confirmDuplicate']);

    expect($clinic->patients()->count())->toBe(1);

    $component->set('confirmDuplicate', true)->call('createPatient')->assertHasNoErrors();
    expect($clinic->patients()->count())->toBe(2);
});

it('books in clinic time using service duration and records an audit', function () {
    ['user' => $user, 'clinic' => $clinic, 'patient' => $patient, 'practitioner' => $practitioner, 'service' => $service] = appointmentContext();

    Livewire::actingAs($user)->test('pages::clinics.appointments', ['clinic' => $clinic])
        ->set('patientId', (string) $patient->id)->set('practitionerId', (string) $practitioner->id)
        ->set('serviceId', (string) $service->id)->set('startsAt', '2026-11-02T09:00')
        ->call('book')->assertHasNoErrors();

    $appointment = Appointment::query()->sole();
    expect($appointment->starts_at->format('Y-m-d H:i:s'))->toBe('2026-11-02 01:00:00')
        ->and($appointment->ends_at->format('Y-m-d H:i:s'))->toBe('2026-11-02 01:30:00')
        ->and($appointment->status)->toBe(AppointmentStatus::Pending)
        ->and($appointment->audits()->where('action', 'created')->exists())->toBeTrue();
});

it('rejects off-grid outside-availability and unavailable-exception bookings', function () {
    ['user' => $user, 'clinic' => $clinic, 'patient' => $patient, 'practitioner' => $practitioner, 'service' => $service] = appointmentContext();
    $scheduler = app(AppointmentScheduler::class);

    expect(fn () => $scheduler->create($clinic, $patient, $practitioner, $service, CarbonImmutable::parse('2026-11-02 09:10', $clinic->timezone)->utc(), $user, fake()->uuid()))
        ->toThrow(ValidationException::class);
    expect(fn () => $scheduler->create($clinic, $patient, $practitioner, $service, CarbonImmutable::parse('2026-11-02 18:00', $clinic->timezone)->utc(), $user, fake()->uuid()))
        ->toThrow(ValidationException::class);

    $practitioner->scheduleExceptions()->create([
        'clinic_id' => $clinic->id, 'type' => 'unavailable',
        'starts_at' => CarbonImmutable::parse('2026-11-02 10:00', $clinic->timezone)->utc(),
        'ends_at' => CarbonImmutable::parse('2026-11-02 11:00', $clinic->timezone)->utc(),
    ]);
    expect(fn () => $scheduler->create($clinic, $patient, $practitioner, $service, CarbonImmutable::parse('2026-11-02 10:00', $clinic->timezone)->utc(), $user, fake()->uuid()))
        ->toThrow(ValidationException::class);
});

it('rejects overlapping practitioner and patient appointments but allows adjacent appointments', function () {
    ['user' => $user, 'clinic' => $clinic, 'patient' => $patient, 'practitioner' => $practitioner, 'service' => $service] = appointmentContext();
    $scheduler = app(AppointmentScheduler::class);
    $start = CarbonImmutable::parse('2026-11-02 09:00', $clinic->timezone)->utc();
    $scheduler->create($clinic, $patient, $practitioner, $service, $start, $user, fake()->uuid());

    $otherPatient = Patient::factory()->for($clinic)->create();
    expect(fn () => $scheduler->create($clinic, $otherPatient, $practitioner, $service, $start->addMinutes(15), $user, fake()->uuid()))
        ->toThrow(ValidationException::class);

    $otherPractitioner = Practitioner::factory()->for($clinic)->create();
    $otherPractitioner->services()->attach($service, ['clinic_id' => $clinic->id]);
    $otherPractitioner->availabilityRules()->create(['clinic_id' => $clinic->id, 'weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00', 'is_active' => true]);
    expect(fn () => $scheduler->create($clinic, $patient, $otherPractitioner, $service, $start->addMinutes(15), $user, fake()->uuid()))
        ->toThrow(ValidationException::class);

    expect($scheduler->create($clinic, $otherPatient, $practitioner, $service, $start->addMinutes(30), $user, fake()->uuid()))->toBeInstanceOf(Appointment::class);
});

it('makes retried booking submissions idempotent', function () {
    ['user' => $user, 'clinic' => $clinic, 'patient' => $patient, 'practitioner' => $practitioner, 'service' => $service] = appointmentContext();
    $scheduler = app(AppointmentScheduler::class);
    $key = fake()->uuid();
    $start = CarbonImmutable::parse('2026-11-02 09:00', $clinic->timezone)->utc();

    $first = $scheduler->create($clinic, $patient, $practitioner, $service, $start, $user, $key);
    $second = $scheduler->create($clinic, $patient, $practitioner, $service, $start, $user, $key);

    expect($second->is($first))->toBeTrue()->and(Appointment::query()->count())->toBe(1);
});

it('returns only available 15 minute slots for the selected date', function () {
    ['user' => $user, 'clinic' => $clinic, 'patient' => $patient, 'practitioner' => $practitioner, 'service' => $service] = appointmentContext();
    $scheduler = app(AppointmentScheduler::class);
    $scheduler->create($clinic, $patient, $practitioner, $service, CarbonImmutable::parse('2026-11-02 09:00', $clinic->timezone)->utc(), $user, fake()->uuid());
    $otherPatient = Patient::factory()->for($clinic)->create();

    $slots = $scheduler->availableSlots($clinic, $otherPatient, $practitioner, $service, CarbonImmutable::parse('2026-11-02', $clinic->timezone));

    expect($slots)->not->toContain('2026-11-02T09:00', '2026-11-02T09:15')
        ->and($slots)->toContain('2026-11-02T09:30', '2026-11-02T10:00');
});

it('reschedules and follows only approved status transitions with audit history', function () {
    ['user' => $user, 'clinic' => $clinic, 'patient' => $patient, 'practitioner' => $practitioner, 'service' => $service] = appointmentContext();
    $scheduler = app(AppointmentScheduler::class);
    $appointment = $scheduler->create($clinic, $patient, $practitioner, $service, CarbonImmutable::parse('2026-11-02 09:00', $clinic->timezone)->utc(), $user, fake()->uuid());

    $rescheduleKey = fake()->uuid();
    $scheduler->reschedule($appointment, CarbonImmutable::parse('2026-11-02 10:00', $clinic->timezone)->utc(), $user, $rescheduleKey);
    $scheduler->reschedule($appointment, CarbonImmutable::parse('2026-11-02 10:00', $clinic->timezone)->utc(), $user, $rescheduleKey);
    $scheduler->transition($appointment->refresh(), AppointmentStatus::Confirmed, $user);
    $scheduler->transition($appointment->refresh(), AppointmentStatus::Completed, $user);

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Completed)
        ->and($appointment->audits()->pluck('action')->all())->toBe(['created', 'rescheduled', 'status_changed', 'status_changed']);
    expect(fn () => $scheduler->transition($appointment, AppointmentStatus::Cancelled, $user))->toThrow(ValidationException::class);
});

it('enforces appointment interval and status constraints in supported databases', function () {
    if (DB::getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite ALTER TABLE does not support the production check constraints.');
    }

    ['user' => $user, 'clinic' => $clinic, 'patient' => $patient, 'practitioner' => $practitioner, 'service' => $service] = appointmentContext();
    $base = [
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'practitioner_id' => $practitioner->id,
        'service_id' => $service->id, 'created_by' => $user->id, 'idempotency_key' => fake()->uuid(),
        'starts_at' => '2026-11-02 01:00:00', 'ends_at' => '2026-11-02 00:30:00', 'status' => 'invalid',
        'created_at' => now(), 'updated_at' => now(),
    ];

    expect(fn () => DB::table('appointments')->insert($base))->toThrow(QueryException::class);
});

it('enforces blocking overlap constraints in PostgreSQL', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL exclusion constraints are verified in PostgreSQL CI.');
    }

    ['user' => $user, 'clinic' => $clinic, 'patient' => $patient, 'practitioner' => $practitioner, 'service' => $service] = appointmentContext();
    $scheduler = app(AppointmentScheduler::class);
    $start = CarbonImmutable::parse('2026-11-02 09:00', $clinic->timezone)->utc();
    $scheduler->create($clinic, $patient, $practitioner, $service, $start, $user, fake()->uuid());

    expect(fn () => DB::table('appointments')->insert([
        'clinic_id' => $clinic->id, 'patient_id' => Patient::factory()->for($clinic)->create()->id,
        'practitioner_id' => $practitioner->id, 'service_id' => $service->id, 'created_by' => $user->id,
        'idempotency_key' => fake()->uuid(), 'starts_at' => $start->addMinutes(15), 'ends_at' => $start->addMinutes(45),
        'status' => AppointmentStatus::Confirmed->value, 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('allows only one of two concurrent PostgreSQL booking attempts', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Deterministic concurrency is verified in PostgreSQL CI.');
    }

    expect(function_exists('pcntl_fork'))->toBeTrue('The PostgreSQL CI runner must provide pcntl for the concurrency test.');

    ['user' => $user, 'clinic' => $clinic, 'patient' => $patient, 'practitioner' => $practitioner, 'service' => $service] = appointmentContext();
    $otherPatient = Patient::factory()->for($clinic)->create();
    $start = CarbonImmutable::parse('2026-11-02 09:00', $clinic->timezone)->utc();
    DB::commit();

    $barrierDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'klinika-concurrency-'.Str::uuid();
    mkdir($barrierDirectory);
    $processIds = [];

    try {
        foreach ([$patient->id, $otherPatient->id] as $attempt => $patientId) {
            $processId = pcntl_fork();
            expect($processId)->toBeGreaterThanOrEqual(0);

            if ($processId === 0) {
                DB::disconnect();
                DB::reconnect();
                touch($barrierDirectory.DIRECTORY_SEPARATOR.'ready-'.$attempt);
                while (! file_exists($barrierDirectory.DIRECTORY_SEPARATOR.'go')) {
                    usleep(1000);
                }

                try {
                    app(AppointmentScheduler::class)->create(
                        Clinic::query()->findOrFail($clinic->id),
                        Patient::query()->findOrFail($patientId),
                        Practitioner::query()->findOrFail($practitioner->id),
                        Service::query()->findOrFail($service->id),
                        $start,
                        User::query()->findOrFail($user->id),
                        (string) Str::uuid(),
                    );
                    file_put_contents($barrierDirectory.DIRECTORY_SEPARATOR.'result-'.$attempt, 'created');
                } catch (Throwable $exception) {
                    file_put_contents($barrierDirectory.DIRECTORY_SEPARATOR.'result-'.$attempt, $exception::class);
                }

                exit(0);
            }

            $processIds[] = $processId;
        }

        $deadline = microtime(true) + 10;
        while ((! file_exists($barrierDirectory.DIRECTORY_SEPARATOR.'ready-0') || ! file_exists($barrierDirectory.DIRECTORY_SEPARATOR.'ready-1')) && microtime(true) < $deadline) {
            usleep(1000);
        }
        expect(file_exists($barrierDirectory.DIRECTORY_SEPARATOR.'ready-0'))->toBeTrue()
            ->and(file_exists($barrierDirectory.DIRECTORY_SEPARATOR.'ready-1'))->toBeTrue();
        touch($barrierDirectory.DIRECTORY_SEPARATOR.'go');

        foreach ($processIds as $processId) {
            pcntl_waitpid($processId, $status);
        }

        $results = [
            file_get_contents($barrierDirectory.DIRECTORY_SEPARATOR.'result-0'),
            file_get_contents($barrierDirectory.DIRECTORY_SEPARATOR.'result-1'),
        ];
        expect(array_count_values($results)['created'] ?? 0)->toBe(1)
            ->and(Appointment::query()->count())->toBe(1);
    } finally {
        DB::table('appointment_audits')->delete();
        DB::table('appointments')->delete();
        DB::table('availability_rules')->delete();
        DB::table('practitioner_service')->delete();
        DB::table('practitioners')->delete();
        DB::table('services')->delete();
        DB::table('patients')->delete();
        DB::table('clinic_user')->delete();
        DB::table('clinics')->delete();
        DB::table('users')->delete();

        foreach (glob($barrierDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($barrierDirectory);
        DB::beginTransaction();
    }
});
