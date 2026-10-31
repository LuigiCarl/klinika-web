<?php

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Services\AppointmentTransitionService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pending requests')] class extends Component {
    public int $previousCount = 0;
    public bool $soundEnabled = true;

    public function mount(): void
    {
        Gate::authorize('viewAny', Appointment::class);
        $this->previousCount = Appointment::query()->where('status', AppointmentStatus::Pending)->count();
    }

    /** @return Collection<int, Appointment> */
    #[Computed]
    public function requests(): Collection
    {
        return Appointment::query()->with(['patient.user', 'doctor.specialty'])->where('status', AppointmentStatus::Pending)->orderBy('starts_at')->get();
    }

    public function checkQueue(): void
    {
        $currentCount = Appointment::query()->where('status', AppointmentStatus::Pending)->count();

        if ($currentCount > $this->previousCount) {
            $diff = $currentCount - $this->previousCount;
            $this->dispatch('new-request-alert', count: $diff);
            Flux::toast(
                variant: 'info',
                heading: __('New request received'),
                text: trans_choice(':count new appointment request awaiting review.|:count new appointment requests awaiting review.', $diff, ['count' => $diff])
            );
        }

        $this->previousCount = $currentCount;
        unset($this->requests);
    }

    public function approve(int $appointmentId, AppointmentTransitionService $service): void
    {
        $appointment = Appointment::query()->findOrFail($appointmentId);
        $service->transitionByReceptionist($appointment, AppointmentStatus::Confirmed, Auth::user());
        $this->previousCount = max(0, $this->previousCount - 1);
        unset($this->requests);
        Flux::toast(variant: 'success', text: __('Appointment approved.'));
    }
}; ?>

<div
    wire:poll.10s="checkQueue"
    class="space-y-6"
    x-data="{
        playChime() {
            if (! $wire.soundEnabled) return;
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                if (ctx.state === 'suspended') {
                    ctx.resume();
                }
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(587.33, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.12);
                gain.gain.setValueAtTime(0.15, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.3);
            } catch (e) {}
        }
    }"
    x-on:new-request-alert.window="playChime()"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2.5">
                <flux:heading size="xl">{{ __('Pending requests') }}</flux:heading>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-950/40 dark:text-emerald-400">
                    <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    {{ __('Live Queue (10s)') }}
                </span>
            </div>
            <flux:text class="mt-1">{{ __('Approve eligible requests or open one to record a rejection reason.') }}</flux:text>
        </div>

        <div class="flex items-center gap-2">
            <flux:button
                size="sm"
                variant="ghost"
                wire:click="$toggle('soundEnabled')"
                :icon="$soundEnabled ? 'speaker-wave' : 'speaker-x-mark'"
                :tooltip="$soundEnabled ? __('Sound alert enabled') : __('Sound alert muted')"
            >
                {{ $soundEnabled ? __('Audio On') : __('Audio Muted') }}
            </flux:button>
        </div>
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
