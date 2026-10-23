<?php

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Appointment calendar')] class extends Component {
    public string $date = '';

    public function mount(): void { Gate::authorize('viewAny', Appointment::class); $this->date = now('Asia/Manila')->toDateString(); }

    /** @return Collection<int, Appointment> */
    #[Computed]
    public function appointments(): Collection
    {
        if (Validator::make(['date' => $this->date], ['date' => ['required', 'date_format:Y-m-d']])->fails()) {
            return new Collection;
        }

        $day = CarbonImmutable::parse($this->date, 'Asia/Manila')->startOfDay();
        return Appointment::query()->with(['patient.user', 'doctor'])->whereBetween('starts_at', [$day->utc(), $day->endOfDay()->utc()])->orderBy('starts_at')->get();
    }
}; ?>

<div class="space-y-6"><div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><flux:heading size="xl">Appointment calendar</flux:heading><flux:text class="mt-1">Daily clinic schedule in Asia/Manila time.</flux:text></div><flux:input wire:model.live="date" type="date" :label="__('Date')" /></div>
    <div class="space-y-3">@forelse ($this->appointments as $appointment)<a wire:key="calendar-{{ $appointment->id }}" href="{{ route('receptionist.appointments.show', $appointment) }}" wire:navigate class="grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 hover:border-teal-500 sm:grid-cols-[8rem_1fr_1fr_auto] sm:items-center dark:border-zinc-700 dark:bg-zinc-900"><p class="font-bold">{{ $appointment->starts_at->timezone('Asia/Manila')->format('g:i A') }}</p><div><p class="font-semibold">{{ $appointment->patient->user->name }}</p><p class="text-xs text-zinc-500">{{ $appointment->reference_code }}</p></div><p class="text-sm">{{ $appointment->doctor->name }}</p><span class="w-fit rounded-full bg-zinc-100 px-3 py-1 text-xs font-bold uppercase text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ str_replace('_', ' ', $appointment->status->value) }}</span></a>@empty<p class="rounded-xl border border-dashed border-zinc-300 p-6 text-zinc-500">No appointments on this date.</p>@endforelse</div>
</div>
