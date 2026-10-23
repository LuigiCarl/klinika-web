<?php

namespace App\Services;

use App\Models\ClinicSetting;
use App\Models\Doctor;
use App\Models\DoctorSlotReservation;
use App\Models\Patient;
use App\Models\PatientSlotReservation;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class AvailabilityService
{
    public function firstAvailableDate(Doctor $doctor, ?Patient $patient = null): ?CarbonImmutable
    {
        $settings = ClinicSetting::query()->findOrFail(1);
        $date = CarbonImmutable::now($settings->timezone)->startOfDay();
        $lastBookableDate = $date->addDays($settings->booking_window_days);

        while ($date->lessThanOrEqualTo($lastBookableDate)) {
            if ($this->slots($doctor, $date, $patient) !== []) {
                return $date;
            }

            $date = $date->addDay();
        }

        return null;
    }

    /** @return list<CarbonImmutable> */
    public function slots(Doctor $doctor, CarbonImmutable $localDate, ?Patient $patient = null): array
    {
        $settings = ClinicSetting::query()->findOrFail(1);
        $timezone = $settings->timezone;
        $date = $localDate->setTimezone($timezone)->startOfDay();
        $schedules = $doctor->schedules()
            ->where('weekday', $date->dayOfWeekIso)
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $date->toDateString()))
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', $date->toDateString()))
            ->orderBy('start_time')
            ->get();

        $slots = [];

        foreach ($schedules as $schedule) {
            $cursor = CarbonImmutable::parse($date->toDateString().' '.$schedule->start_time, $timezone);
            $scheduleEnd = CarbonImmutable::parse($date->toDateString().' '.$schedule->end_time, $timezone);

            while ($cursor->addMinutes($doctor->appointment_duration_minutes)->lessThanOrEqualTo($scheduleEnd)) {
                try {
                    $this->assertBookable($doctor, $patient, $cursor->utc());
                    $slots[] = $cursor->utc();
                } catch (ValidationException) {
                    // An unavailable candidate is omitted from the public availability result.
                }

                $cursor = $cursor->addMinutes(15);
            }
        }

        return $slots;
    }

    public function assertBookable(Doctor $doctor, ?Patient $patient, CarbonImmutable $startsAt): void
    {
        $settings = ClinicSetting::query()->findOrFail(1);
        $timezone = $settings->timezone;
        $localStart = $startsAt->setTimezone($timezone);
        $localEnd = $localStart->addMinutes($doctor->appointment_duration_minutes);
        $now = CarbonImmutable::now();

        if (! $doctor->is_active) {
            $this->fail('doctor', 'The selected doctor is not accepting appointment requests.');
        }

        if ($localStart->minute % 15 !== 0 || $localStart->second !== 0) {
            $this->fail('starts_at', 'Appointment times must align to the 15-minute scheduling grid.');
        }

        $minimumStart = $now->addHours(max($settings->minimum_lead_hours, $settings->minimum_review_hours));
        $maximumStart = $now->addDays($settings->booking_window_days);

        if ($startsAt->lessThan($minimumStart)) {
            $this->fail('starts_at', 'The selected time does not meet the minimum booking lead time.');
        }

        if ($startsAt->greaterThan($maximumStart)) {
            $this->fail('starts_at', 'The selected time is beyond the booking window.');
        }

        $date = $localStart->toDateString();
        $covered = $doctor->schedules()
            ->where('weekday', $localStart->dayOfWeekIso)
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $date))
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', $date))
            ->get()
            ->contains(fn ($schedule): bool => $schedule->start_time <= $localStart->format('H:i:s')
                && $schedule->end_time >= $localEnd->format('H:i:s'));

        if (! $covered) {
            $this->fail('starts_at', 'The selected interval is outside the doctor’s working hours.');
        }

        $endsAt = $startsAt->addMinutes($doctor->appointment_duration_minutes);
        $unavailable = $doctor->unavailabilities()
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        if ($unavailable) {
            $this->fail('starts_at', 'The doctor is unavailable during the selected interval.');
        }

        $slotStarts = $this->slotStarts($startsAt, $doctor->appointment_duration_minutes);

        if (DoctorSlotReservation::query()->where('doctor_id', $doctor->id)->whereIn('slot_starts_at', $slotStarts)->exists()) {
            $this->fail('starts_at', 'The selected doctor time is no longer available.');
        }

        if ($patient !== null && PatientSlotReservation::query()->where('patient_id', $patient->id)->whereIn('slot_starts_at', $slotStarts)->exists()) {
            $this->fail('starts_at', 'You already have an appointment during the selected time.');
        }
    }

    /** @return list<CarbonImmutable> */
    public function slotStarts(CarbonImmutable $startsAt, int $durationMinutes): array
    {
        $slots = [];

        foreach (range(0, intdiv($durationMinutes, 15) - 1) as $index) {
            $slots[] = $startsAt->addMinutes($index * 15);
        }

        return $slots;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
