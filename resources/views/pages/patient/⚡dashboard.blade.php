<?php

use App\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Patient dashboard')] class extends Component {
    public function mount(): void { Gate::authorize('viewAny', Appointment::class); }

    /** @return Collection<int, Appointment> */
    #[Computed]
    public function upcoming(): Collection
    {
        $patient = Auth::user()->patient;

        if ($patient === null) {
            return new Collection;
        }

        return $patient->appointments()->with('doctor.specialty')
            ->whereIn('status', [AppointmentStatus::Pending, AppointmentStatus::Confirmed])
            ->where('starts_at', '>', now())->orderBy('starts_at')->limit(3)->get();
    }
}; ?>

<div class="space-y-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><flux:heading size="xl">Welcome, {{ auth()->user()->name }}</flux:heading><flux:text class="mt-1">Manage your appointment requests securely.</flux:text></div><flux:button :href="route('doctors.index')" variant="primary" wire:navigate>Browse doctors</flux:button></div>
    <section><div class="flex items-center justify-between"><flux:heading size="lg">Upcoming appointments</flux:heading><flux:link :href="route('patient.appointments.index')" wire:navigate>View all</flux:link></div>
        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($this->upcoming as $appointment)
                <a href="{{ route('patient.appointments.show', $appointment) }}" wire:navigate class="rounded-xl border border-zinc-200 bg-white p-5 hover:border-teal-500 dark:border-zinc-700 dark:bg-zinc-900"><p class="font-semibold">{{ $appointment->doctor->name }}</p><p class="text-sm text-zinc-500">{{ $appointment->doctor->specialty->name }}</p><p class="mt-3 text-sm">{{ $appointment->starts_at->timezone('Asia/Manila')->format('M j, Y · g:i A') }}</p><p class="mt-2 text-xs font-semibold uppercase text-teal-700">{{ $appointment->status->value }}</p></a>
            @empty
                <p class="rounded-xl border border-dashed border-zinc-300 p-5 text-zinc-500 md:col-span-2">You have no upcoming appointment requests.</p>
            @endforelse
        </div>
    </section>
</div>
