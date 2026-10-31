<?php

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Specialty;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Clinic Analytics & KPIs')] class extends Component
{
    #[Url]
    public string $range = '30';

    /** @return array{total: int, completed: int, confirmed: int, pending: int, cancelled: int, noShow: int, completionRate: float, noShowRate: float, cancellationRate: float, averageLeadHours: float} */
    #[Computed]
    public function kpis(): array
    {
        $appointments = $this->filteredAppointments();
        $total = $appointments->count();

        $completed = $appointments->where('status', AppointmentStatus::Completed)->count();
        $confirmed = $appointments->where('status', AppointmentStatus::Confirmed)->count();
        $pending = $appointments->where('status', AppointmentStatus::Pending)->count();
        $cancelled = $appointments->whereIn('status', [AppointmentStatus::Cancelled, AppointmentStatus::Rejected, AppointmentStatus::Expired])->count();
        $noShow = $appointments->where('status', AppointmentStatus::NoShow)->count();

        $resolvedTotal = $completed + $noShow + $cancelled;
        $completionRate = $resolvedTotal > 0 ? round(($completed / $resolvedTotal) * 100, 1) : 0.0;
        $noShowRate = $resolvedTotal > 0 ? round(($noShow / $resolvedTotal) * 100, 1) : 0.0;
        $cancellationRate = $total > 0 ? round(($cancelled / $total) * 100, 1) : 0.0;

        $leadHoursSum = 0.0;
        foreach ($appointments as $appointment) {
            if ($appointment->created_at !== null && $appointment->starts_at !== null) {
                $diff = $appointment->created_at->diffInMinutes($appointment->starts_at, false) / 60;
                $leadHoursSum += max(0.0, $diff);
            }
        }
        $averageLeadHours = $total > 0 ? round($leadHoursSum / $total, 1) : 0.0;

        return [
            'total' => $total,
            'completed' => $completed,
            'confirmed' => $confirmed,
            'pending' => $pending,
            'cancelled' => $cancelled,
            'noShow' => $noShow,
            'completionRate' => $completionRate,
            'noShowRate' => $noShowRate,
            'cancellationRate' => $cancellationRate,
            'averageLeadHours' => $averageLeadHours,
        ];
    }

    /** @return list<array{id: int, name: string, specialty: string, total: int, completed: int, confirmed: int, noShow: int, utilizationRate: float}> */
    #[Computed]
    public function doctorUtilization(): array
    {
        $appointments = $this->filteredAppointments();
        $doctors = Doctor::query()->with('specialty')->orderBy('name')->get();

        $rows = [];
        foreach ($doctors as $doctor) {
            $docAppointments = $appointments->where('doctor_id', $doctor->id);
            $total = $docAppointments->count();
            $completed = $docAppointments->where('status', AppointmentStatus::Completed)->count();
            $confirmed = $docAppointments->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn])->count();
            $noShow = $docAppointments->where('status', AppointmentStatus::NoShow)->count();
            $activeBookings = $completed + $confirmed;

            $utilizationRate = $total > 0 ? round(($activeBookings / $total) * 100, 1) : 0.0;

            $rows[] = [
                'id' => $doctor->id,
                'name' => $doctor->name,
                'specialty' => $doctor->specialty->name,
                'total' => $total,
                'completed' => $completed,
                'confirmed' => $confirmed,
                'noShow' => $noShow,
                'utilizationRate' => $utilizationRate,
            ];
        }

        return $rows;
    }

    /** @return list<array{name: string, count: int, percentage: float}> */
    #[Computed]
    public function specialtyDemand(): array
    {
        $appointments = $this->filteredAppointments();
        $total = $appointments->count();
        $specialties = Specialty::query()->orderBy('name')->get();

        $rows = [];
        foreach ($specialties as $specialty) {
            $count = $appointments->filter(fn (Appointment $a): bool => $a->doctor->specialty_id === $specialty->id)->count();
            $rows[] = [
                'name' => $specialty->name,
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0.0,
            ];
        }

        return $rows;
    }

    /** @return Collection<int, Appointment> */
    private function filteredAppointments(): Collection
    {
        $query = Appointment::query()->with(['doctor.specialty']);

        if ($this->range !== 'all') {
            $days = match ($this->range) {
                '7' => 7,
                '90' => 90,
                default => 30,
            };
            $cutoff = CarbonImmutable::now('Asia/Manila')->subDays($days)->startOfDay()->utc();
            $query->where('starts_at', '>=', $cutoff);
        }

        return $query->get();
    }
};
?>

<div class="flex w-full flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Clinic Analytics & KPIs') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Monitor appointment throughput, doctor utilization, lead times, and specialty demand.') }}</flux:text>
        </div>
        <div class="flex items-center gap-3">
            <flux:select wire:model.live="range" aria-label="{{ __('Timeframe') }}">
                <flux:select.option value="7">{{ __('Last 7 days') }}</flux:select.option>
                <flux:select.option value="30">{{ __('Last 30 days') }}</flux:select.option>
                <flux:select.option value="90">{{ __('Last 90 days') }}</flux:select.option>
                <flux:select.option value="all">{{ __('All time') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="{{ __('Key Performance Indicators') }}">
        <article class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Total Appointments') }}</p>
            <p class="mt-2 text-3xl font-bold text-zinc-900 dark:text-white">{{ $this->kpis['total'] }}</p>
            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                {{ $this->kpis['confirmed'] }} {{ __('confirmed') }} · {{ $this->kpis['pending'] }} {{ __('pending') }}
            </p>
        </article>

        <article class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Completion Rate') }}</p>
            <p class="mt-2 text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($this->kpis['completionRate'], 1) }}%</p>
            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                {{ $this->kpis['completed'] }} {{ __('completed consultations') }}
            </p>
        </article>

        <article class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('No-Show Rate') }}</p>
            <p class="mt-2 text-3xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($this->kpis['noShowRate'], 1) }}%</p>
            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                {{ $this->kpis['noShow'] }} {{ __('missed appointments') }} · {{ number_format($this->kpis['cancellationRate'], 1) }}% {{ __('cancelled') }}
            </p>
        </article>

        <article class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Average Lead Time') }}</p>
            <p class="mt-2 text-3xl font-bold text-teal-600 dark:text-teal-400">{{ number_format($this->kpis['averageLeadHours'], 1) }}h</p>
            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('Advance booking window before slot start') }}
            </p>
        </article>
    </section>

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="xl:col-span-2 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Doctor Utilization & Outcomes') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Appointment volume and active fulfillment rate per doctor.') }}</flux:text>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50 text-xs uppercase text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-400">
                        <tr>
                            <th class="px-5 py-3">{{ __('Doctor') }}</th>
                            <th class="px-5 py-3">{{ __('Total') }}</th>
                            <th class="px-5 py-3">{{ __('Completed') }}</th>
                            <th class="px-5 py-3">{{ __('Confirmed') }}</th>
                            <th class="px-5 py-3">{{ __('No-Show') }}</th>
                            <th class="px-5 py-3">{{ __('Fulfillment') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($this->doctorUtilization as $row)
                            <tr wire:key="doctor-util-{{ $row['id'] }}">
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-zinc-900 dark:text-white">{{ $row['name'] }}</div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $row['specialty'] }}</div>
                                </td>
                                <td class="px-5 py-4 font-medium">{{ $row['total'] }}</td>
                                <td class="px-5 py-4 text-emerald-600 dark:text-emerald-400">{{ $row['completed'] }}</td>
                                <td class="px-5 py-4 text-teal-600 dark:text-teal-400">{{ $row['confirmed'] }}</td>
                                <td class="px-5 py-4 text-amber-600 dark:text-amber-400">{{ $row['noShow'] }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-24 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                            <div class="h-full rounded-full bg-teal-600" style="width: {{ min(100, $row['utilizationRate']) }}%"></div>
                                        </div>
                                        <span class="text-xs font-semibold">{{ number_format($row['utilizationRate'], 1) }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-6 text-center text-zinc-500">{{ __('No doctors registered yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Specialty Demand') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Share of appointment requests across medical specialties.') }}</flux:text>

            <div class="mt-5 flex flex-col gap-4">
                @forelse ($this->specialtyDemand as $item)
                    <div wire:key="specialty-demand-{{ $loop->index }}" class="space-y-1.5">
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium text-zinc-900 dark:text-white">{{ $item['name'] }}</span>
                            <span class="text-zinc-500 dark:text-zinc-400">{{ $item['count'] }} ({{ number_format($item['percentage'], 1) }}%)</span>
                        </div>
                        <div class="h-2.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <div class="h-full rounded-full bg-teal-600 dark:bg-teal-500" style="width: {{ min(100, $item['percentage']) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500">{{ __('No specialties configured yet.') }}</p>
                @endforelse
            </div>
        </section>
    </div>
</div>

