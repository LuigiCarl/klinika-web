<?php

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Specialty;
use Illuminate\Database\QueryException;

it('prevents deleting a specialty referenced by a doctor', function () {
    $specialty = Specialty::factory()->create();
    Doctor::factory()->for($specialty)->create();

    expect(fn () => $specialty->delete())->toThrow(QueryException::class);
});

it('supports multiple working intervals for one doctor and weekday', function () {
    $doctor = Doctor::factory()->create();
    DoctorSchedule::factory()->for($doctor)->create(['weekday' => 1, 'start_time' => '09:00:00', 'end_time' => '12:00:00']);
    DoctorSchedule::factory()->for($doctor)->create(['weekday' => 1, 'start_time' => '13:00:00', 'end_time' => '17:00:00']);

    expect($doctor->schedules()->where('weekday', 1)->count())->toBe(2);
});
