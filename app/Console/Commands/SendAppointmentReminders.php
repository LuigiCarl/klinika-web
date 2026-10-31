<?php

namespace App\Console\Commands;

use App\AppointmentStatus;
use App\Mail\AppointmentReminder;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('appointments:send-reminders')]
#[Description('Send email reminders for confirmed appointments scheduled within the next 24 hours')]
class SendAppointmentReminders extends Command
{
    public function handle(): int
    {
        $now = CarbonImmutable::now('Asia/Manila')->utc();
        $windowEnd = $now->addHours(24);

        $appointments = Appointment::query()
            ->with(['doctor.specialty', 'patient.user'])
            ->where('status', AppointmentStatus::Confirmed)
            ->whereBetween('starts_at', [$now, $windowEnd])
            ->get();

        $sent = 0;

        foreach ($appointments as $appointment) {
            Mail::to($appointment->patient->user->email)->send(new AppointmentReminder($appointment));
            $sent++;
        }

        $this->info("Dispatched {$sent} appointment reminder(s).");

        return self::SUCCESS;
    }
}
