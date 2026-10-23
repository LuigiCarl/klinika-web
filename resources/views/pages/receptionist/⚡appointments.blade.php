<?php

use App\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Appointments')] class extends Component {
    public string $search = '';
    public string $status = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Appointment::class);
        $requestedStatus = (string) request()->query('status', '');
        $this->status = in_array($requestedStatus, array_column(AppointmentStatus::cases(), 'value'), true) ? $requestedStatus : '';
    }

    /** @return Collection<int, Appointment> */
    #[Computed]
    public function appointments(): Collection
    {
        $this->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::enum(AppointmentStatus::class)]]);
        return Appointment::query()->with(['patient.user', 'doctor.specialty'])
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->search !== '', function ($query): void {
                $term = '%'.addcslashes($this->search, '%_\\').'%';
                $query->where(fn ($query) => $query->where('reference_code', 'like', $term)
                    ->orWhereHas('patient.user', fn ($query) => $query->where('name', 'like', $term)->orWhere('email', 'like', $term))
                    ->orWhereHas('doctor', fn ($query) => $query->where('name', 'like', $term)));
            })->orderByDesc('starts_at')->limit(100)->get();
    }
}; ?>

<div class="space-y-6"><div><flux:heading size="xl">Appointments</flux:heading><flux:text class="mt-1">Search current appointments and history.</flux:text></div><div class="grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 sm:grid-cols-2 dark:border-zinc-700 dark:bg-zinc-900"><flux:input wire:model.live.debounce.300ms="search" :label="__('Search patient, doctor, email, or reference')"/><flux:select wire:model.live="status" :label="__('Status')"><flux:select.option value="">All statuses</flux:select.option>@foreach (AppointmentStatus::cases() as $case)<flux:select.option wire:key="status-{{ $case->value }}" :value="$case->value">{{ str_replace('_', ' ', ucfirst($case->value)) }}</flux:select.option>@endforeach</flux:select></div>
    <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">@forelse ($this->appointments as $appointment)<a wire:key="appointment-{{ $appointment->id }}" href="{{ route('receptionist.appointments.show', $appointment) }}" wire:navigate class="grid gap-2 p-4 hover:bg-zinc-50 sm:grid-cols-4 dark:hover:bg-zinc-800"><div><p class="font-semibold">{{ $appointment->patient->user->name }}</p><p class="text-xs text-zinc-500">{{ $appointment->reference_code }}</p></div><p class="text-sm">{{ $appointment->doctor->name }}</p><p class="text-sm">{{ $appointment->starts_at->timezone('Asia/Manila')->format('M j, Y · g:i A') }}</p><p class="text-sm font-semibold uppercase text-teal-700 sm:text-right">{{ str_replace('_', ' ', $appointment->status->value) }}</p></a>@empty<p class="p-6 text-zinc-500">No appointments match these filters.</p>@endforelse</div>
</div>
