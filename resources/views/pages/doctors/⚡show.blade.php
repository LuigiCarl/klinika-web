<?php

use App\Models\Doctor;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.public'), Title('Doctor details')] class extends Component {
    public Doctor $doctor;

    public function mount(Doctor $doctor): void
    {
        abort_unless($doctor->is_active && $doctor->specialty()->where('is_active', true)->exists(), 404);
        $this->doctor = $doctor->load(['specialty', 'schedules' => fn ($query) => $query->orderBy('weekday')->orderBy('start_time')]);
    }
}; ?>

<div class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
    <a href="{{ route('doctors.index') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-900 dark:text-teal-400 dark:hover:text-teal-300" wire:navigate>&larr; All doctors</a>
    <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 sm:p-8">
        <p class="font-semibold text-teal-700 dark:text-teal-400">{{ $doctor->specialty->name }}</p>
        <h1 class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">{{ $doctor->name }}</h1>
        <p class="mt-2 text-slate-600 dark:text-zinc-400">Standard appointment: {{ $doctor->appointment_duration_minutes }} minutes</p>
        <h2 class="mt-8 text-lg font-bold text-slate-900 dark:text-white">Regular clinic hours</h2>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @forelse ($doctor->schedules as $schedule)
                <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm dark:bg-zinc-700/50">
                    <span class="font-semibold text-slate-900 dark:text-zinc-100">{{ Carbon\CarbonImmutable::now()->startOfWeek()->addDays($schedule->weekday - 1)->format('l') }}</span>
                    <span class="text-slate-600 dark:text-zinc-300">{{ Carbon\CarbonImmutable::parse($schedule->start_time)->format('g:i A') }}–{{ Carbon\CarbonImmutable::parse($schedule->end_time)->format('g:i A') }}</span>
                </div>
            @empty
                <p class="text-slate-600 dark:text-zinc-400">No regular hours are currently published.</p>
            @endforelse
        </div>
        @auth
            @if (auth()->user()->hasRole(App\Role::Patient))
                <a href="{{ route('patient.book', $doctor) }}" class="mt-8 inline-flex rounded-lg bg-teal-700 px-5 py-3 font-semibold text-white hover:bg-teal-800 dark:bg-teal-600 dark:hover:bg-teal-700" wire:navigate>Request an appointment</a>
            @endif
        @else
            <a href="{{ route('login') }}" class="mt-8 inline-flex rounded-lg bg-teal-700 px-5 py-3 font-semibold text-white hover:bg-teal-800 dark:bg-teal-600 dark:hover:bg-teal-700">Log in to book</a>
        @endauth
    </section>
</div>
