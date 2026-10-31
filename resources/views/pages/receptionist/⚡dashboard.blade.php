<?php

use App\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Receptionist dashboard')] class extends Component {
    public function mount(): void { Gate::authorize('viewAny', Appointment::class); }

    /** @return array{pending: int, today: int, checked_in: int} */
    #[Computed]
    public function counts(): array
    {
        $today = now('Asia/Manila')->startOfDay();
        return [
            'pending' => Appointment::query()->where('status', AppointmentStatus::Pending)->count(),
            'today' => Appointment::query()->whereBetween('starts_at', [$today->clone()->utc(), $today->clone()->endOfDay()->utc()])->count(),
            'checked_in' => Appointment::query()->where('status', AppointmentStatus::CheckedIn)->count(),
        ];
    }

    /** @return Collection<int, Appointment> */
    #[Computed]
    public function nextAppointments(): Collection
    {
        return Appointment::query()->with(['patient.user', 'doctor'])->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn])
            ->where('starts_at', '>=', now()->subHours(2))->orderBy('starts_at')->limit(6)->get();
    }
}; ?>

<div wire:poll.15s class="space-y-8"><div><flux:heading size="xl">Receptionist dashboard</flux:heading><flux:text class="mt-1">Review requests and manage today's patient flow.</flux:text></div>
    <div class="grid gap-4 sm:grid-cols-3"><a href="{{ route('receptionist.pending') }}" wire:navigate class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900"><p class="text-sm text-zinc-500">Pending review</p><p class="mt-2 text-3xl font-bold text-amber-600">{{ $this->counts['pending'] }}</p></a><a href="{{ route('receptionist.calendar') }}" wire:navigate class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900"><p class="text-sm text-zinc-500">Appointments today</p><p class="mt-2 text-3xl font-bold">{{ $this->counts['today'] }}</p></a><a href="{{ route('receptionist.appointments.index', ['status' => 'checked_in']) }}" wire:navigate class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900"><p class="text-sm text-zinc-500">Currently checked in</p><p class="mt-2 text-3xl font-bold text-teal-700">{{ $this->counts['checked_in'] }}</p></a></div>
    <section><div class="flex items-center justify-between"><flux:heading size="lg">Upcoming patient flow</flux:heading><flux:link :href="route('receptionist.appointments.index')" wire:navigate>Search all</flux:link></div><div class="mt-4 divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">
        @forelse ($this->nextAppointments as $appointment)<a wire:key="next-{{ $appointment->id }}" href="{{ route('receptionist.appointments.show', $appointment) }}" wire:navigate class="grid gap-2 p-4 hover:bg-zinc-50 sm:grid-cols-4 dark:hover:bg-zinc-800"><span class="font-semibold">{{ $appointment->starts_at->timezone('Asia/Manila')->format('M j · g:i A') }}</span><span>{{ $appointment->patient->user->name }}</span><span>{{ $appointment->doctor->name }}</span><span class="text-sm font-semibold uppercase text-teal-700 sm:text-right">{{ str_replace('_', ' ', $appointment->status->value) }}</span></a>@empty<p class="p-5 text-zinc-500">No upcoming confirmed appointments.</p>@endforelse
    </div></section>
</div>
