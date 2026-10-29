<?php

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\ClinicSetting;
use App\Services\AppointmentCancellationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Appointment details')] class extends Component {
    public Appointment $appointment;

    public function mount(Appointment $appointment): void
    {
        Gate::authorize('view', $appointment);
        $this->appointment = $appointment->load('doctor.specialty');
    }

    #[Computed]
    public function canCancel(): bool
    {
        $hours = ClinicSetting::query()->findOrFail(1)->cancellation_cutoff_hours;
        return in_array($this->appointment->status, [AppointmentStatus::Pending, AppointmentStatus::Confirmed], true)
            && $this->appointment->starts_at->greaterThan(now()->addHours($hours));
    }

    public function cancel(AppointmentCancellationService $service): void
    {
        $this->appointment = $service->cancelByPatient($this->appointment, Auth::user())->load('doctor.specialty');
        unset($this->canCancel);
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-6"><flux:link :href="route('patient.appointments.index')" wire:navigate>&larr; My appointments</flux:link>
    <section class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900"><div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><flux:heading size="xl">{{ $appointment->doctor->name }}</flux:heading><flux:text>{{ $appointment->doctor->specialty->name }}</flux:text></div><span class="w-fit rounded-full bg-teal-50 px-3 py-1 text-xs font-bold uppercase text-teal-800">{{ str_replace('_', ' ', $appointment->status->value) }}</span></div>
        <dl class="mt-6 grid gap-5 sm:grid-cols-2"><div><dt class="text-sm text-zinc-500">Reference</dt><dd class="font-semibold">{{ $appointment->reference_code }}</dd></div><div><dt class="text-sm text-zinc-500">Date and time</dt><dd class="font-semibold">{{ $appointment->starts_at->timezone('Asia/Manila')->format('F j, Y · g:i A') }}</dd></div><div><dt class="text-sm text-zinc-500">Duration</dt><dd>{{ $appointment->duration_minutes }} minutes</dd></div><div><dt class="text-sm text-zinc-500">Request expires</dt><dd>{{ $appointment->expires_at->timezone('Asia/Manila')->format('M j, Y · g:i A') }}</dd></div></dl>
        <flux:error name="appointment" />
        <flux:error name="rate_limit" />
        @if ($this->canCancel)
            <div class="mt-8 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Cancellation releases this time, but does not guarantee another slot.</p>
                <div class="mt-3 flex items-center gap-3">
                    <flux:modal.trigger name="confirm-patient-cancel-modal">
                        <flux:button variant="danger">Cancel appointment</flux:button>
                    </flux:modal.trigger>
                    <span wire:loading wire:target="cancel" role="status" class="text-sm text-zinc-500">Cancelling...</span>
                </div>

                <flux:modal name="confirm-patient-cancel-modal" class="min-w-[22rem] max-w-md space-y-6">
                    <div>
                        <flux:heading size="lg">Cancel appointment?</flux:heading>
                        <flux:subheading class="mt-2">
                            Are you sure you want to cancel your appointment with <strong class="text-zinc-900 dark:text-zinc-100">{{ $appointment->doctor->name }}</strong> on <strong class="text-zinc-900 dark:text-zinc-100">{{ $appointment->starts_at->timezone('Asia/Manila')->format('F j, Y · g:i A') }}</strong>?
                        </flux:subheading>
                        <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                            Cancellation releases this time, but does not guarantee another slot. You may book again at any time.
                        </p>
                    </div>

                    <div class="flex justify-end gap-3">
                        <flux:modal.close>
                            <flux:button variant="filled">Keep appointment</flux:button>
                        </flux:modal.close>
                        <flux:modal.close>
                            <flux:button variant="danger" wire:click="cancel" wire:loading.attr="disabled" wire:target="cancel">
                                Cancel appointment
                            </flux:button>
                        </flux:modal.close>
                    </div>
                </flux:modal>
            </div>
        @elseif (in_array($appointment->status, [AppointmentStatus::Pending, AppointmentStatus::Confirmed], true))
            <p class="mt-8 rounded-lg bg-amber-50 p-4 text-sm text-amber-900">The self-service cancellation cutoff has passed. Please contact the clinic.</p>
        @endif
    </section>
</div>
