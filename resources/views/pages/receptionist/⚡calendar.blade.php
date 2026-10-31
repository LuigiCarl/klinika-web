<?php

use App\Models\Appointment;
use App\Models\Doctor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Appointment calendar')] class extends Component {
    public string $date = '';
    public string $doctorId = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Appointment::class);
        $this->date = now('Asia/Manila')->toDateString();
    }

    /** @return Collection<int, Doctor> */
    #[Computed]
    public function doctors(): Collection
    {
        return Doctor::query()->where('is_active', true)->orderBy('name')->get();
    }

    /** @return Collection<int, Appointment> */
    #[Computed]
    public function appointments(): Collection
    {
        if (Validator::make(['date' => $this->date], ['date' => ['required', 'date_format:Y-m-d']])->fails()) {
            return new Collection;
        }

        $day = CarbonImmutable::parse($this->date, 'Asia/Manila')->startOfDay();

        return Appointment::query()
            ->with(['patient.user', 'doctor.specialty'])
            ->whereBetween('starts_at', [$day->utc(), $day->endOfDay()->utc()])
            ->when($this->doctorId !== '', fn ($query) => $query->where('doctor_id', (int) $this->doctorId))
            ->orderBy('starts_at')
            ->get();
    }

    public function exportCsv(): StreamedResponse
    {
        Gate::authorize('viewAny', Appointment::class);

        $appointments = $this->appointments;
        $date = $this->date;
        $fileName = "clinic-manifest-{$date}.csv";

        return response()->streamDownload(function () use ($appointments): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Time',
                'Reference Code',
                'Patient Name',
                'Contact Phone',
                'Doctor',
                'Specialty',
                'Duration (mins)',
                'Status',
            ]);

            foreach ($appointments as $appointment) {
                fputcsv($handle, [
                    $appointment->starts_at->timezone('Asia/Manila')->format('g:i A'),
                    $appointment->reference_code,
                    $appointment->patient->user->name ?? 'N/A',
                    $appointment->patient->phone ?? 'N/A',
                    $appointment->doctor->name ?? 'N/A',
                    $appointment->doctor->specialty->name ?? 'N/A',
                    $appointment->duration_minutes,
                    strtoupper(str_replace('_', ' ', $appointment->status->value)),
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }
}; ?>

<div class="space-y-6">
    <!-- Screen View (Hidden when printing) -->
    <div class="print:hidden space-y-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Appointment calendar') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Daily clinic schedule and patient manifest in Asia/Manila time.') }}</flux:text>
            </div>

            <div class="flex items-center gap-2">
                <flux:button
                    wire:click="exportCsv"
                    icon="arrow-down-tray"
                    variant="filled"
                    size="sm"
                    :disabled="$this->appointments->isEmpty()"
                >
                    {{ __('Export CSV') }}
                </flux:button>
                <flux:button
                    type="button"
                    onclick="window.print()"
                    icon="printer"
                    variant="filled"
                    size="sm"
                    :disabled="$this->appointments->isEmpty()"
                >
                    {{ __('Print Manifest') }}
                </flux:button>
            </div>
        </div>

        <div class="grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 sm:grid-cols-2 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:input wire:model.live="date" type="date" :label="__('Date')" required />
            <flux:select wire:model.live="doctorId" :label="__('Filter by Doctor')">
                <flux:select.option value="">{{ __('All Doctors') }}</flux:select.option>
                @foreach ($this->doctors as $doctor)
                    <flux:select.option wire:key="doctor-filter-{{ $doctor->id }}" :value="$doctor->id">
                        {{ $doctor->name }} ({{ $doctor->specialty->name }})
                    </flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="space-y-3">
            @forelse ($this->appointments as $appointment)
                <a
                    wire:key="calendar-{{ $appointment->id }}"
                    href="{{ route('receptionist.appointments.show', $appointment) }}"
                    wire:navigate
                    class="grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 hover:border-teal-500 sm:grid-cols-[8rem_1fr_1fr_auto] sm:items-center dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <p class="font-bold text-zinc-900 dark:text-zinc-100">{{ $appointment->starts_at->timezone('Asia/Manila')->format('g:i A') }}</p>
                    <div>
                        <p class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $appointment->patient->user->name }}</p>
                        <p class="text-xs text-zinc-500">{{ $appointment->reference_code }} · {{ $appointment->patient->phone ?? 'No phone' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $appointment->doctor->name }}</p>
                        <p class="text-xs text-zinc-500">{{ $appointment->doctor->specialty->name }}</p>
                    </div>
                    <span class="w-fit rounded-full bg-zinc-100 px-3 py-1 text-xs font-bold uppercase text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                        {{ str_replace('_', ' ', $appointment->status->value) }}
                    </span>
                </a>
            @empty
                <p class="rounded-xl border border-dashed border-zinc-300 p-6 text-zinc-500 dark:border-zinc-700">
                    {{ __('No appointments scheduled for this date and filter.') }}
                </p>
            @endforelse
        </div>
    </div>

    <!-- Printable Clinic Manifest Layout (Visible ONLY during print) -->
    <div class="hidden print:block text-black">
        <div class="border-b border-black pb-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">KLINIKA MEDICAL CLINIC</h1>
                    <p class="text-sm">Daily Patient Appointment Manifest</p>
                </div>
                <div class="text-right text-xs">
                    <p><strong>Date:</strong> {{ rescue(fn () => \Carbon\Carbon::parse($this->date)->format('F j, Y (l)'), $this->date) }}</p>
                    <p><strong>Generated:</strong> {{ now('Asia/Manila')->format('M j, Y g:i A') }}</p>
                </div>
            </div>
        </div>

        <table class="w-full text-left text-xs border-collapse border border-zinc-400">
            <thead>
                <tr class="bg-zinc-100 border-b border-zinc-400">
                    <th class="p-2 border-r border-zinc-400">Time</th>
                    <th class="p-2 border-r border-zinc-400">Reference</th>
                    <th class="p-2 border-r border-zinc-400">Patient Name</th>
                    <th class="p-2 border-r border-zinc-400">Contact</th>
                    <th class="p-2 border-r border-zinc-400">Doctor</th>
                    <th class="p-2 border-r border-zinc-400">Status</th>
                    <th class="p-2">Check-in / Signature</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->appointments as $appointment)
                    <tr class="border-b border-zinc-300">
                        <td class="p-2 border-r border-zinc-300 font-bold whitespace-nowrap">{{ $appointment->starts_at->timezone('Asia/Manila')->format('g:i A') }}</td>
                        <td class="p-2 border-r border-zinc-300 font-mono text-[10px]">{{ $appointment->reference_code }}</td>
                        <td class="p-2 border-r border-zinc-300 font-semibold">{{ $appointment->patient->user->name }}</td>
                        <td class="p-2 border-r border-zinc-300">{{ $appointment->patient->phone ?? '—' }}</td>
                        <td class="p-2 border-r border-zinc-300">{{ $appointment->doctor->name }} ({{ $appointment->doctor->specialty->name }})</td>
                        <td class="p-2 border-r border-zinc-300 uppercase font-semibold text-[10px]">{{ str_replace('_', ' ', $appointment->status->value) }}</td>
                        <td class="p-2 w-32 border-zinc-300"></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-4 text-center italic">No appointments on this schedule.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="mt-8 flex justify-between text-xs pt-4 border-t border-zinc-300">
            <div>
                <p>Total Scheduled Appointments: <strong>{{ $this->appointments->count() }}</strong></p>
            </div>
            <div class="text-right">
                <p>Receptionist On Duty: ____________________________</p>
            </div>
        </div>
    </div>
</div>
