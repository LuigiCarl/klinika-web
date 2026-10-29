<?php

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Services\AppointmentTransitionService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pending requests')] class extends Component {
    public function mount(): void { Gate::authorize('viewAny', Appointment::class); }

    /** @return Collection<int, Appointment> */
    #[Computed]
    public function requests(): Collection
    {
        return Appointment::query()->with(['patient.user', 'doctor.specialty'])->where('status', AppointmentStatus::Pending)->orderBy('starts_at')->get();
    }

    public function approve(int $appointmentId, AppointmentTransitionService $service): void
    {
        $appointment = Appointment::query()->findOrFail($appointmentId);
        $service->transitionByReceptionist($appointment, AppointmentStatus::Confirmed, Auth::user());
        unset($this->requests);
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">Pending requests</flux:heading>
        <flux:text class="mt-1">Approve eligible requests or open one to record a rejection reason.</flux:text>
    </div>
    <flux:error name="appointment" />
    <flux:error name="rate_limit" />

    <div class="grid gap-4">
        @forelse ($this->requests as $appointment)
            <article wire:key="pending-{{ $appointment->id }}" class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="grid gap-4 md:grid-cols-[1fr_auto] md:items-center">
                    <div>
                        <p class="font-semibold">{{ $appointment->patient->user->name }}</p>
                        <p class="text-sm text-zinc-500">{{ $appointment->doctor->name }} · {{ $appointment->doctor->specialty->name }}</p>
                        <p class="mt-2 text-sm">{{ $appointment->starts_at->timezone('Asia/Manila')->format('F j, Y · g:i A') }} · {{ $appointment->reference_code }}</p>
                        <p class="mt-1 text-xs text-zinc-500">Review before {{ $appointment->expires_at->timezone('Asia/Manila')->format('M j · g:i A') }}</p>
                    </div>
                    <div class="flex gap-2">
                        <flux:modal.trigger name="approve-appointment-{{ $appointment->id }}">
                            <flux:button variant="primary">Approve</flux:button>
                        </flux:modal.trigger>
                        <flux:button :href="route('receptionist.appointments.show', $appointment)" wire:navigate>Review</flux:button>
                    </div>
                </div>

                <flux:modal name="approve-appointment-{{ $appointment->id }}" class="min-w-[22rem] max-w-md space-y-6">
                    <div>
                        <flux:heading size="lg">Approve appointment</flux:heading>
                        <flux:subheading class="mt-2">
                            Are you sure you want to approve the appointment for <strong class="text-zinc-900 dark:text-zinc-100">{{ $appointment->patient->user->name }}</strong> with <strong class="text-zinc-900 dark:text-zinc-100">{{ $appointment->doctor->name }}</strong>?
                        </flux:subheading>
                        <div class="mt-4 rounded-lg bg-zinc-50 p-3 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                            <p><span class="font-medium text-zinc-700 dark:text-zinc-300">Schedule:</span> {{ $appointment->starts_at->timezone('Asia/Manila')->format('F j, Y · g:i A') }}</p>
                            <p class="mt-1"><span class="font-medium text-zinc-700 dark:text-zinc-300">Reference:</span> {{ $appointment->reference_code }}</p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <flux:modal.close>
                            <flux:button variant="filled">Cancel</flux:button>
                        </flux:modal.close>
                        <flux:modal.close>
                            <flux:button wire:click="approve({{ $appointment->id }})" variant="primary" wire:loading.attr="disabled">Approve request</flux:button>
                        </flux:modal.close>
                    </div>
                </flux:modal>
            </article>
        @empty
            <p class="rounded-xl border border-dashed border-zinc-300 p-6 text-zinc-500">No pending requests.</p>
        @endforelse
    </div>
</div>
