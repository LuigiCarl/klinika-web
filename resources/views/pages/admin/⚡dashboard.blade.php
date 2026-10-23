<?php

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\User;
use App\Role;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /** @return array{activeDoctors: int, pendingRequests: int, todayAppointments: int, receptionists: int} */
    #[Computed]
    public function statistics(): array
    {
        $today = CarbonImmutable::now('Asia/Manila');

        return [
            'activeDoctors' => Doctor::query()->where('is_active', true)->count(),
            'pendingRequests' => Appointment::query()->where('status', AppointmentStatus::Pending)->count(),
            'todayAppointments' => Appointment::query()
                ->whereBetween('starts_at', [$today->startOfDay()->utc(), $today->endOfDay()->utc()])
                ->count(),
            'receptionists' => User::query()
                ->where('role', Role::Receptionist)
                ->where('is_active', true)
                ->count(),
        ];
    }

    /** @return Collection<int, Appointment> */
    #[Computed]
    public function recentAppointments(): Collection
    {
        return Appointment::query()
            ->with(['doctor.specialty', 'patient.user'])
            ->latest()
            ->limit(5)
            ->get();
    }
};
?>

<div class="flex w-full flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Administrator dashboard') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Monitor clinic activity and manage scheduling resources.') }}</flux:text>
        </div>
        <flux:button :href="route('admin.doctors')" variant="primary" wire:navigate>
            {{ __('Manage doctors and schedules') }}
        </flux:button>
    </div>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="{{ __('Clinic overview') }}">
        @foreach ([
            ['label' => __('Active doctors'), 'value' => $this->statistics['activeDoctors'], 'detail' => __('Accepting appointment requests')],
            ['label' => __('Pending requests'), 'value' => $this->statistics['pendingRequests'], 'detail' => __('Awaiting receptionist review')],
            ['label' => __("Today's appointments"), 'value' => $this->statistics['todayAppointments'], 'detail' => __('Scheduled in clinic time')],
            ['label' => __('Receptionists'), 'value' => $this->statistics['receptionists'], 'detail' => __('Active staff accounts')],
        ] as $statistic)
            <article wire:key="admin-stat-{{ $loop->index }}" class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $statistic['label'] }}</p>
                <p class="mt-2 text-3xl font-bold text-zinc-900 dark:text-white">{{ $statistic['value'] }}</p>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $statistic['detail'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Recent appointment requests') }}</flux:heading>
            <flux:text class="mt-1">{{ __('The five most recently submitted requests.') }}</flux:text>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse ($this->recentAppointments as $appointment)
                <div wire:key="recent-appointment-{{ $appointment->id }}" class="grid gap-2 p-5 sm:grid-cols-[1fr_auto] sm:items-center">
                    <div>
                        <p class="font-semibold text-zinc-900 dark:text-white">{{ $appointment->patient->user->name }}</p>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $appointment->doctor->name }} · {{ $appointment->doctor->specialty->name }}</p>
                    </div>
                    <div class="sm:text-right">
                        <p class="text-sm font-medium">{{ $appointment->starts_at->timezone('Asia/Manila')->format('M j, Y · g:i A') }}</p>
                        <p class="mt-1 text-xs font-bold uppercase tracking-wide text-teal-700 dark:text-teal-300">{{ str_replace('_', ' ', $appointment->status->value) }}</p>
                    </div>
                </div>
            @empty
                <p class="p-6 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No appointment requests have been submitted yet.') }}</p>
            @endforelse
        </div>
    </section>
</div>
