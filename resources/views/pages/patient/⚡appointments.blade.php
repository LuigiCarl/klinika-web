<?php

use App\Models\Appointment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('My appointments')] class extends Component {
    public function mount(): void { Gate::authorize('viewAny', Appointment::class); abort_unless(Auth::user()->patient !== null, 403); }

    /** @return Collection<int, Appointment> */
    #[Computed]
    public function appointments(): Collection
    {
        return Auth::user()->patient->appointments()->with('doctor.specialty')->latest('starts_at')->get();
    }
}; ?>

<div class="mx-auto max-w-5xl"><div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><flux:heading size="xl">My appointments</flux:heading><flux:text class="mt-1">Your appointment request history and current status.</flux:text></div><flux:button :href="route('doctors.index')" wire:navigate>Browse doctors</flux:button></div>
    <div class="mt-6 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700"><div class="divide-y divide-zinc-200 dark:divide-zinc-700">
        @forelse ($this->appointments as $appointment)
            <a wire:key="appointment-{{ $appointment->id }}" href="{{ route('patient.appointments.show', $appointment) }}" wire:navigate class="grid gap-2 bg-white p-5 hover:bg-zinc-50 sm:grid-cols-4 sm:items-center dark:bg-zinc-900 dark:hover:bg-zinc-800"><div class="sm:col-span-2"><p class="font-semibold">{{ $appointment->doctor->name }}</p><p class="text-sm text-zinc-500">{{ $appointment->doctor->specialty->name }} · {{ $appointment->reference_code }}</p></div><p class="text-sm">{{ $appointment->starts_at->timezone('Asia/Manila')->format('M j, Y · g:i A') }}</p><p class="text-sm font-semibold uppercase text-teal-700 sm:text-right">{{ str_replace('_', ' ', $appointment->status->value) }}</p></a>
        @empty
            <p class="bg-white p-6 text-zinc-500 dark:bg-zinc-900">No appointments yet.</p>
        @endforelse
    </div></div>
</div>
