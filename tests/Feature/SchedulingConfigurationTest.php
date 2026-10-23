<?php

use App\Models\Clinic;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\User;
use App\Role;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('redirects guests from clinic configuration', function () {
    $this->get(route('clinics.index'))->assertRedirect(route('login'));
});

it('forbids patients from clinic configuration', function (Role $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('clinics.index'))
        ->assertForbidden();
})->with([
    'patient' => Role::Patient,
]);

it('creates a clinic and attaches the administrator as a member', function () {
    $administrator = User::factory()->administrator()->create();

    Livewire::actingAs($administrator)
        ->test('pages::clinics.index')
        ->set('name', 'Makati Medical Clinic')
        ->set('timezone', 'Asia/Manila')
        ->call('createClinic')
        ->assertHasNoErrors();

    $clinic = Clinic::query()->where('slug', 'makati-medical-clinic')->sole();

    expect($clinic->users()->whereKey($administrator->id)->exists())->toBeTrue();
});

it('prevents an administrator from configuring a clinic without membership', function () {
    $administrator = User::factory()->administrator()->create();
    $clinic = Clinic::factory()->create();

    $this->actingAs($administrator)
        ->get(route('clinics.scheduling', $clinic))
        ->assertNotFound();
});

it('allows a clinic administrator to configure services practitioners and schedules', function () {
    $administrator = User::factory()->administrator()->create();
    $clinic = Clinic::factory()->create();
    $clinic->users()->attach($administrator, ['role' => Role::Administrator->value]);

    $component = Livewire::actingAs($administrator)
        ->test('pages::clinics.scheduling', ['clinic' => $clinic])
        ->set('serviceName', 'General Consultation')
        ->set('durationMinutes', 30)
        ->call('createService')
        ->assertHasNoErrors()
        ->set('practitionerName', 'Dr. Maria Santos')
        ->set('licenseNumber', 'PRC-1001')
        ->call('createPractitioner')
        ->assertHasNoErrors();

    $service = Service::query()->whereBelongsTo($clinic)->sole();
    $practitioner = Practitioner::query()->whereBelongsTo($clinic)->sole();

    $component
        ->set('eligibilityPractitionerId', (string) $practitioner->id)
        ->set('eligibilityServiceId', (string) $service->id)
        ->call('assignService')
        ->assertHasNoErrors()
        ->set('availabilityPractitionerId', (string) $practitioner->id)
        ->set('weekday', 1)
        ->set('availabilityStartsAt', '09:00')
        ->set('availabilityEndsAt', '17:00')
        ->call('createAvailability')
        ->assertHasNoErrors()
        ->set('exceptionPractitionerId', (string) $practitioner->id)
        ->set('exceptionType', 'unavailable')
        ->set('exceptionStartsAt', '2026-11-02T09:00')
        ->set('exceptionEndsAt', '2026-11-02T12:00')
        ->set('exceptionReason', 'Training')
        ->call('createException')
        ->assertHasNoErrors();

    expect($practitioner->services()->whereKey($service->id)->exists())->toBeTrue()
        ->and($practitioner->availabilityRules()->count())->toBe(1)
        ->and($practitioner->scheduleExceptions()->count())->toBe(1);

    $availability = $practitioner->availabilityRules()->sole();
    $exception = $practitioner->scheduleExceptions()->sole();

    $component
        ->call('removeServiceAssignment', $practitioner->id, $service->id)
        ->call('deleteAvailability', $practitioner->id, $availability->id)
        ->call('deleteException', $practitioner->id, $exception->id)
        ->assertHasNoErrors();

    expect($practitioner->services()->exists())->toBeFalse()
        ->and($practitioner->availabilityRules()->exists())->toBeFalse()
        ->and($practitioner->scheduleExceptions()->exists())->toBeFalse();
});

it('rejects cross-clinic practitioner and service assignments', function () {
    $administrator = User::factory()->administrator()->create();
    $clinic = Clinic::factory()->create();
    $otherClinic = Clinic::factory()->create();
    $clinic->users()->attach($administrator, ['role' => Role::Administrator->value]);
    $practitioner = Practitioner::factory()->for($clinic)->create();
    $otherService = Service::factory()->for($otherClinic)->create();

    Livewire::actingAs($administrator)
        ->test('pages::clinics.scheduling', ['clinic' => $clinic])
        ->set('eligibilityPractitionerId', (string) $practitioner->id)
        ->set('eligibilityServiceId', (string) $otherService->id)
        ->call('assignService')
        ->assertHasErrors(['eligibilityServiceId']);

    expect($practitioner->services()->exists())->toBeFalse();
});

it('validates that schedule intervals end after they start', function () {
    $administrator = User::factory()->administrator()->create();
    $clinic = Clinic::factory()->create();
    $clinic->users()->attach($administrator, ['role' => Role::Administrator->value]);
    $practitioner = Practitioner::factory()->for($clinic)->create();

    Livewire::actingAs($administrator)
        ->test('pages::clinics.scheduling', ['clinic' => $clinic])
        ->set('availabilityPractitionerId', (string) $practitioner->id)
        ->set('availabilityStartsAt', '17:00')
        ->set('availabilityEndsAt', '09:00')
        ->call('createAvailability')
        ->assertHasErrors(['availabilityEndsAt']);

    expect($practitioner->availabilityRules()->exists())->toBeFalse();
});

it('uses the clinic membership role for configuration authorization', function () {
    $user = User::factory()->create(['role' => Role::Receptionist]);
    $clinic = Clinic::factory()->create();
    $clinic->users()->attach($user, ['role' => Role::Administrator->value]);

    $this->actingAs($user)
        ->get(route('clinics.scheduling', $clinic))
        ->assertOk();

    $clinic->users()->updateExistingPivot($user->id, ['role' => Role::Receptionist->value]);

    $this->actingAs($user)
        ->get(route('clinics.scheduling', $clinic))
        ->assertNotFound();
});

it('stores schedule exceptions in utc from the clinic timezone', function () {
    $administrator = User::factory()->administrator()->create();
    $clinic = Clinic::factory()->create(['timezone' => 'Asia/Manila']);
    $clinic->users()->attach($administrator, ['role' => Role::Administrator->value]);
    $practitioner = Practitioner::factory()->for($clinic)->create();

    Livewire::actingAs($administrator)
        ->test('pages::clinics.scheduling', ['clinic' => $clinic])
        ->set('exceptionPractitionerId', (string) $practitioner->id)
        ->set('exceptionType', 'unavailable')
        ->set('exceptionStartsAt', '2026-11-02T09:00')
        ->set('exceptionEndsAt', '2026-11-02T10:00')
        ->call('createException')
        ->assertHasNoErrors();

    $exception = $practitioner->scheduleExceptions()->sole();

    expect($exception->starts_at->format('Y-m-d H:i:s'))->toBe('2026-11-02 01:00:00')
        ->and($exception->ends_at->format('Y-m-d H:i:s'))->toBe('2026-11-02 02:00:00');
});

it('allows administrators to correct and deactivate configuration records', function () {
    $administrator = User::factory()->administrator()->create();
    $clinic = Clinic::factory()->create();
    $clinic->users()->attach($administrator, ['role' => Role::Administrator->value]);
    $service = Service::factory()->for($clinic)->create();
    $practitioner = Practitioner::factory()->for($clinic)->create();

    Livewire::actingAs($administrator)
        ->test('pages::clinics.scheduling', ['clinic' => $clinic])
        ->set('clinicName', 'Updated Clinic')
        ->set('clinicTimezone', 'Asia/Tokyo')
        ->call('updateClinic')
        ->call('editService', $service->id)
        ->set('serviceName', 'Updated Consultation')
        ->set('durationMinutes', 45)
        ->call('createService')
        ->call('toggleService', $service->id)
        ->call('editPractitioner', $practitioner->id)
        ->set('practitionerName', 'Dr. Updated')
        ->call('createPractitioner')
        ->call('togglePractitioner', $practitioner->id)
        ->assertHasNoErrors();

    expect($clinic->refresh()->name)->toBe('Updated Clinic')
        ->and($clinic->timezone)->toBe('Asia/Tokyo')
        ->and($service->refresh()->name)->toBe('Updated Consultation')
        ->and($service->duration_minutes)->toBe(45)
        ->and($service->is_active)->toBeFalse()
        ->and($practitioner->refresh()->display_name)->toBe('Dr. Updated')
        ->and($practitioner->is_active)->toBeFalse();
});

it('enforces the :constraint scheduling constraint in the database', function (string $constraint) {
    if (DB::getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite cannot add the production check constraints with ALTER TABLE.');
    }

    $clinic = Clinic::factory()->create();
    $practitioner = Practitioner::factory()->for($clinic)->create();

    $writeInvalidRecord = match ($constraint) {
        'service duration' => fn () => DB::table('services')->insert([
            'clinic_id' => $clinic->id,
            'name' => 'Invalid Service',
            'duration_minutes' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]),
        'availability weekday' => fn () => DB::table('availability_rules')->insert([
            'clinic_id' => $clinic->id,
            'practitioner_id' => $practitioner->id,
            'weekday' => 8,
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]),
        'availability interval' => fn () => DB::table('availability_rules')->insert([
            'clinic_id' => $clinic->id,
            'practitioner_id' => $practitioner->id,
            'weekday' => 1,
            'starts_at' => '17:00:00',
            'ends_at' => '09:00:00',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]),
        'effective date range' => fn () => DB::table('availability_rules')->insert([
            'clinic_id' => $clinic->id,
            'practitioner_id' => $practitioner->id,
            'weekday' => 1,
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'effective_from' => '2026-12-31',
            'effective_until' => '2026-01-01',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]),
        'schedule exception interval' => fn () => DB::table('schedule_exceptions')->insert([
            'clinic_id' => $clinic->id,
            'practitioner_id' => $practitioner->id,
            'type' => 'unavailable',
            'starts_at' => '2026-11-02 10:00:00',
            'ends_at' => '2026-11-02 09:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]),
    };

    expect($writeInvalidRecord)->toThrow(QueryException::class);
})->with([
    'service duration',
    'availability weekday',
    'availability interval',
    'effective date range',
    'schedule exception interval',
]);
