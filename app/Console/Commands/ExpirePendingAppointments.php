<?php

namespace App\Console\Commands;

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Services\AppointmentTransitionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('appointments:expire {--limit=1000 : Maximum pending requests to inspect in this run}')]
#[Description('Expire overdue pending appointment requests and release their reservations')]
class ExpirePendingAppointments extends Command
{
    public function handle(AppointmentTransitionService $transitions): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 5000],
        ]);

        if ($limit === false) {
            $this->error('The --limit option must be an integer between 1 and 5000.');

            return self::INVALID;
        }

        $appointmentIds = Appointment::query()
            ->where('status', AppointmentStatus::Pending)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');
        $expired = 0;
        $failed = 0;

        foreach ($appointmentIds as $appointmentId) {
            try {
                $appointment = Appointment::query()->find((int) $appointmentId);

                if ($appointment !== null && $transitions->expireIfOverdue($appointment)) {
                    $expired++;
                }
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        $this->info("Expired {$expired} appointment request(s); {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
