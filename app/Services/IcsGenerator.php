<?php

namespace App\Services;

use App\Models\Appointment;
use Carbon\CarbonImmutable;

class IcsGenerator
{
    public static function generate(Appointment $appointment): string
    {
        $appointment->loadMissing(['doctor.specialty', 'patient.user']);

        $uid = $appointment->reference_code.'@klinika.local';
        $dtStamp = CarbonImmutable::now()->utc()->format('Ymd\THis\Z');
        $dtStart = $appointment->starts_at->utc()->format('Ymd\THis\Z');
        $dtEnd = $appointment->ends_at->utc()->format('Ymd\THis\Z');
        $summary = self::escape("Medical Consultation with {$appointment->doctor->name} ({$appointment->doctor->specialty->name})");
        $description = self::escape("Reference Code: {$appointment->reference_code}\\nPatient: {$appointment->patient->user->name}\\nDoctor: {$appointment->doctor->name}");
        $location = self::escape('Klinika Medical Center');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Klinika Web//Booking Engine//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:REQUEST',
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$dtStamp}",
            "DTSTART:{$dtStart}",
            "DTEND:{$dtEnd}",
            "SUMMARY:{$summary}",
            "DESCRIPTION:{$description}",
            "LOCATION:{$location}",
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", $lines)."\r\n";
    }

    private static function escape(string $value): string
    {
        return str_replace([',', ';'], ['\,', '\;'], $value);
    }
}
