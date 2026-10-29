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
            @if ($appointment->status === AppointmentStatus::Pending)
                <div class="grid gap-6 lg:grid-cols-2">
                    <div>
                        <flux:modal.trigger name="approve-appointment-modal">
                            <flux:button variant="primary">Approve request</flux:button>
                        </flux:modal.trigger>

                        <flux:modal name="approve-appointment-modal" class="min-w-[22rem] max-w-md space-y-6">
                            <div>
                                <flux:heading size="lg">Approve appointment request</flux:heading>
                                <flux:subheading class="mt-2">
                                    Are you sure you want to approve this request for <strong class="text-zinc-900 dark:text-zinc-100">{{ $appointment->patient->user->name }}</strong>?
                                </flux:subheading>
                                <div class="mt-4 rounded-lg bg-zinc-50 p-3 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                    <p><span class="font-medium text-zinc-700 dark:text-zinc-300">Doctor:</span> {{ $appointment->doctor->name }} ({{ $appointment->doctor->specialty->name }})</p>
                                    <p class="mt-1"><span class="font-medium text-zinc-700 dark:text-zinc-300">Schedule:</span> {{ $appointment->starts_at->timezone('Asia/Manila')->format('F j, Y · g:i A') }}</p>
                                </div>
                            </div>

                            <div class="flex justify-end gap-3">
                                <flux:modal.close>
                                    <flux:button variant="filled">Cancel</flux:button>
                                </flux:modal.close>
                                <flux:modal.close>
                                    <flux:button wire:click="approve" variant="primary" wire:loading.attr="disabled">Approve request</flux:button>
                                </flux:modal.close>
                            </div>
                        </flux:modal>
                    </div>

                    <form wire:submit="reject" class="flex flex-col gap-3">
                        <flux:textarea wire:model="rejectionReason" :label="__('Rejection reason')" rows="2" placeholder="Explain why the request cannot be accommodated..." required />
                        <div>
                            <flux:modal.trigger name="reject-appointment-modal">
                                <flux:button type="button" variant="danger">Reject request</flux:button>
                            </flux:modal.trigger>
                        </div>

                        <flux:modal name="reject-appointment-modal" class="min-w-[22rem] max-w-md space-y-6">
                            <div>
                                <flux:heading size="lg">Reject appointment request</flux:heading>
                                <flux:subheading class="mt-2">
                                    Are you sure you want to reject this appointment request? This action cannot be undone.
                                </flux:subheading>
                                @if ($rejectionReason)
                                    <div class="mt-4 rounded-lg bg-red-50 p-3 text-xs text-red-900 dark:bg-red-950/50 dark:text-red-200">
                                        <span class="font-semibold">Reason:</span> {{ $rejectionReason }}
                                    </div>
                                @endif
                            </div>

                            <div class="flex justify-end gap-3">
                                <flux:modal.close>
                                    <flux:button variant="filled">Cancel</flux:button>
                                </flux:modal.close>
                                <flux:modal.close>
                                    <flux:button type="submit" variant="danger" wire:loading.attr="disabled">Confirm rejection</flux:button>
                                </flux:modal.close>
                            </div>
                        </flux:modal>
                    </form>
                </div>
            @elseif ($appointment->status === AppointmentStatus::Confirmed)
                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:modal.trigger name="check-in-modal">
                            <flux:button variant="primary">Check in patient</flux:button>
                        </flux:modal.trigger>

                        <flux:modal name="check-in-modal" class="min-w-[22rem] max-w-md space-y-6">
                            <div>
                                <flux:heading size="lg">Check in patient</flux:heading>
                                <flux:subheading class="mt-2">
                                    Mark <strong class="text-zinc-900 dark:text-zinc-100">{{ $appointment->patient->user->name }}</strong> as arrived and checked in for their appointment?
                                </flux:subheading>
                            </div>

                            <div class="flex justify-end gap-3">
                                <flux:modal.close>
                                    <flux:button variant="filled">Cancel</flux:button>
                                </flux:modal.close>
                                <flux:modal.close>
                                    <flux:button wire:click="checkIn" variant="primary" wire:loading.attr="disabled">Confirm check-in</flux:button>
                                </flux:modal.close>
                            </div>
                        </flux:modal>

                        <flux:modal.trigger name="noshow-modal">
                            <flux:button variant="filled">Mark no-show</flux:button>
                        </flux:modal.trigger>

                        <flux:modal name="noshow-modal" class="min-w-[22rem] max-w-md space-y-6">
                            <div>
                                <flux:heading size="lg">Mark appointment as no-show</flux:heading>
                                <flux:subheading class="mt-2">
                                    Are you sure you want to mark <strong class="text-zinc-900 dark:text-zinc-100">{{ $appointment->patient->user->name }}</strong> as a no-show? This records a missed visit.
                                </flux:subheading>
                            </div>

                            <div class="flex justify-end gap-3">
                                <flux:modal.close>
                                    <flux:button variant="filled">Cancel</flux:button>
                                </flux:modal.close>
                                <flux:modal.close>
                                    <flux:button wire:click="markNoShow" variant="danger" wire:loading.attr="disabled">Mark no-show</flux:button>
                                </flux:modal.close>
                            </div>
                        </flux:modal>
                    </div>

                    <form wire:submit="cancel" class="flex flex-col gap-3">
                        <flux:textarea wire:model="cancellationReason" :label="__('Late cancellation reason')" rows="2" placeholder="Reason for staff cancellation..." required />
                        <div>
                            <flux:modal.trigger name="staff-cancel-modal">
                                <flux:button type="button" variant="danger">Staff cancellation</flux:button>
                            </flux:modal.trigger>
                        </div>

                        <flux:modal name="staff-cancel-modal" class="min-w-[22rem] max-w-md space-y-6">
                            <div>
                                <flux:heading size="lg">Cancel confirmed appointment</flux:heading>
                                <flux:subheading class="mt-2">
                                    Are you sure you want to cancel this confirmed appointment? The reserved doctor slot will be released.
                                </flux:subheading>
                                @if ($cancellationReason)
                                    <div class="mt-4 rounded-lg bg-red-50 p-3 text-xs text-red-900 dark:bg-red-950/50 dark:text-red-200">
                                        <span class="font-semibold">Reason:</span> {{ $cancellationReason }}
                                    </div>
                                @endif
                            </div>

                            <div class="flex justify-end gap-3">
                                <flux:modal.close>
                                    <flux:button variant="filled">Cancel</flux:button>
                                </flux:modal.close>
                                <flux:modal.close>
                                    <flux:button type="submit" variant="danger" wire:loading.attr="disabled">Confirm cancellation</flux:button>
                                </flux:modal.close>
                            </div>
                        </flux:modal>
                    </form>
                </div>
            @elseif ($appointment->status === AppointmentStatus::CheckedIn)
                <flux:modal.trigger name="complete-visit-modal">
                    <flux:button variant="primary">Complete visit</flux:button>
                </flux:modal.trigger>

                <flux:modal name="complete-visit-modal" class="min-w-[22rem] max-w-md space-y-6">
                    <div>
                        <flux:heading size="lg">Complete visit</flux:heading>
                        <flux:subheading class="mt-2">
                            Mark the visit for <strong class="text-zinc-900 dark:text-zinc-100">{{ $appointment->patient->user->name }}</strong> as completed?
                        </flux:subheading>
                    </div>

                    <div class="flex justify-end gap-3">
                        <flux:modal.close>
                            <flux:button variant="filled">Cancel</flux:button>
                        </flux:modal.close>
                        <flux:modal.close>
                            <flux:button wire:click="complete" variant="primary" wire:loading.attr="disabled">Mark completed</flux:button>
                        </flux:modal.close>
                    </div>
                </flux:modal>
            @endif
        </div></section>
    <section><flux:heading size="lg">Audit history</flux:heading><div class="mt-3 divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">@forelse ($appointment->events->sortByDesc('created_at') as $event)<div wire:key="event-{{ $event->id }}" class="p-4"><div class="flex flex-col gap-1 sm:flex-row sm:justify-between"><p class="font-semibold">{{ str_replace('_', ' ', ucfirst($event->event_type)) }}</p><p class="text-xs text-zinc-500">{{ $event->created_at->timezone('Asia/Manila')->format('M j, Y · g:i A') }}</p></div><p class="text-sm text-zinc-500">{{ $event->actor?->name ?? 'System' }}@if ($event->reason) · {{ $event->reason }}@endif</p></div>@empty<p class="p-5 text-zinc-500">No events recorded.</p>@endforelse</div></section>
</div>
