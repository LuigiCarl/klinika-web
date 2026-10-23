<?php

use App\Models\Appointment;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Request received')] class extends Component {
    public Appointment $appointment;
    public function mount(Appointment $appointment): void { Gate::authorize('view', $appointment); $this->appointment = $appointment->load('doctor.specialty'); }
}; ?>

<div class="mx-auto max-w-2xl"><section class="rounded-xl border border-zinc-200 bg-white p-6 text-center dark:border-zinc-700 dark:bg-zinc-900 sm:p-10"><div class="mx-auto flex size-12 items-center justify-center rounded-full bg-teal-100 text-2xl text-teal-800">✓</div><flux:heading size="xl" class="mt-5">Appointment request received</flux:heading><flux:text class="mt-2">Your request is pending clinic review. Save your reference number.</flux:text><p class="mt-5 text-2xl font-bold tracking-wide">{{ $appointment->reference_code }}</p><div class="mt-6 rounded-lg bg-zinc-50 p-4 text-left text-sm dark:bg-zinc-800"><p class="font-semibold">{{ $appointment->doctor->name }} · {{ $appointment->doctor->specialty->name }}</p><p class="mt-1">{{ $appointment->starts_at->timezone('Asia/Manila')->format('F j, Y · g:i A') }}</p></div><div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row"><flux:button :href="route('patient.appointments.show', $appointment)" variant="primary" wire:navigate>View appointment</flux:button><flux:button :href="route('patient.dashboard')" wire:navigate>Back to dashboard</flux:button></div></section></div>
