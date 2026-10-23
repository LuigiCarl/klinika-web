<?php

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Services\AppointmentTransitionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Manage appointment')] class extends Component {
    public Appointment $appointment;
    public string $rejectionReason = '';
    public string $cancellationReason = '';

    public function mount(Appointment $appointment): void
    {
        Gate::authorize('view', $appointment);
        $this->appointment = $this->loaded($appointment);
    }

    public function approve(AppointmentTransitionService $service): void { $this->performTransition($service, AppointmentStatus::Confirmed); }
    public function checkIn(AppointmentTransitionService $service): void { $this->performTransition($service, AppointmentStatus::CheckedIn); }
    public function complete(AppointmentTransitionService $service): void { $this->performTransition($service, AppointmentStatus::Completed); }
    public function markNoShow(AppointmentTransitionService $service): void { $this->performTransition($service, AppointmentStatus::NoShow); }

    public function reject(AppointmentTransitionService $service): void
    {
        $validated = $this->validate(['rejectionReason' => ['required', 'string', 'min:3', 'max:500']]);
        $this->performTransition($service, AppointmentStatus::Rejected, $validated['rejectionReason']);
        $this->reset('rejectionReason');
    }

    public function cancel(AppointmentTransitionService $service): void
    {
        $validated = $this->validate(['cancellationReason' => ['required', 'string', 'min:3', 'max:500']]);
        $this->performTransition($service, AppointmentStatus::Cancelled, $validated['cancellationReason']);
        $this->reset('cancellationReason');
    }

    private function performTransition(AppointmentTransitionService $service, AppointmentStatus $status, ?string $reason = null): void
    {
        $this->appointment = $this->loaded($service->transitionByReceptionist($this->appointment, $status, Auth::user(), $reason));
    }

    private function loaded(Appointment $appointment): Appointment
    {
        return $appointment->load(['patient.user', 'doctor.specialty', 'events.actor']);
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-6"><flux:link :href="route('receptionist.appointments.index')" wire:navigate>&larr; Appointments</flux:link><section class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900"><div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><flux:heading size="xl">{{ $appointment->patient->user->name }}</flux:heading><flux:text>{{ $appointment->patient->user->email }} · {{ $appointment->patient->phone ?: 'No phone provided' }}</flux:text></div><span class="w-fit rounded-full bg-teal-50 px-3 py-1 text-xs font-bold uppercase text-teal-800">{{ str_replace('_', ' ', $appointment->status->value) }}</span></div><dl class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4"><div><dt class="text-sm text-zinc-500">Reference</dt><dd class="font-semibold">{{ $appointment->reference_code }}</dd></div><div><dt class="text-sm text-zinc-500">Doctor</dt><dd class="font-semibold">{{ $appointment->doctor->name }}</dd></div><div><dt class="text-sm text-zinc-500">Schedule</dt><dd class="font-semibold">{{ $appointment->starts_at->timezone('Asia/Manila')->format('M j, Y · g:i A') }}</dd></div><div><dt class="text-sm text-zinc-500">Duration</dt><dd>{{ $appointment->duration_minutes }} minutes</dd></div></dl><flux:error name="appointment" />
        <flux:error name="rate_limit" />
        <div class="mt-8 border-t border-zinc-200 pt-6 dark:border-zinc-700">
            @if ($appointment->status === AppointmentStatus::Pending)<div class="grid gap-4 lg:grid-cols-2"><div><flux:button wire:click="approve" variant="primary" wire:confirm="Approve this request?">Approve request</flux:button></div><form wire:submit="reject" class="flex flex-col gap-3"><flux:textarea wire:model="rejectionReason" :label="__('Rejection reason')" rows="2" required/><flux:button type="submit" variant="danger" wire:confirm="Reject this request?">Reject request</flux:button></form></div>
            @elseif ($appointment->status === AppointmentStatus::Confirmed)<div class="grid gap-4 lg:grid-cols-2"><div><flux:button wire:click="checkIn" variant="primary" wire:confirm="Check this patient in?">Check in patient</flux:button><flux:button class="ms-2" wire:click="markNoShow" wire:confirm="Mark this appointment as no-show?">Mark no-show</flux:button></div><form wire:submit="cancel" class="flex flex-col gap-3"><flux:textarea wire:model="cancellationReason" :label="__('Late cancellation reason')" rows="2" required/><flux:button type="submit" variant="danger" wire:confirm="Cancel this confirmed appointment?">Staff cancellation</flux:button></form></div>
            @elseif ($appointment->status === AppointmentStatus::CheckedIn)<flux:button wire:click="complete" variant="primary" wire:confirm="Mark this visit completed?">Complete visit</flux:button>
            @endif
        </div></section>
    <section><flux:heading size="lg">Audit history</flux:heading><div class="mt-3 divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">@forelse ($appointment->events->sortByDesc('created_at') as $event)<div wire:key="event-{{ $event->id }}" class="p-4"><div class="flex flex-col gap-1 sm:flex-row sm:justify-between"><p class="font-semibold">{{ str_replace('_', ' ', ucfirst($event->event_type)) }}</p><p class="text-xs text-zinc-500">{{ $event->created_at->timezone('Asia/Manila')->format('M j, Y · g:i A') }}</p></div><p class="text-sm text-zinc-500">{{ $event->actor?->name ?? 'System' }}@if ($event->reason) · {{ $event->reason }}@endif</p></div>@empty<p class="p-5 text-zinc-500">No events recorded.</p>@endforelse</div></section>
</div>
