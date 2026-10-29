<?php

use App\Models\Doctor;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.public'), Title('Our doctors')] class extends Component {
    /** @return Collection<int, Doctor> */
    #[Computed]
    public function doctors(): Collection
    {
        return Doctor::query()->with('specialty')->where('is_active', true)
            ->whereHas('specialty', fn ($query) => $query->where('is_active', true))->orderBy('name')->get();
    }
}; ?>

<div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="max-w-2xl">
        <p class="font-semibold text-teal-700 dark:text-teal-400">Single-clinic care</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-4xl">Choose your doctor</h1>
        <p class="mt-3 text-slate-600 dark:text-zinc-400">Review active doctors, their specialties, and their regular clinic schedules.</p>
    </div>
    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->doctors as $doctor)
            <article wire:key="doctor-{{ $doctor->id }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition-colors dark:border-zinc-700 dark:bg-zinc-800">
                <p class="text-sm font-semibold text-teal-700 dark:text-teal-400">{{ $doctor->specialty->name }}</p>
                <h2 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">{{ $doctor->name }}</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-zinc-400">{{ $doctor->appointment_duration_minutes }}-minute appointments</p>
                <a href="{{ route('doctors.show', $doctor) }}" class="mt-6 inline-flex font-semibold text-teal-700 hover:text-teal-900 dark:text-teal-400 dark:hover:text-teal-300" wire:navigate>View doctor &rarr;</a>
            </article>
        @empty
            <p class="rounded-xl border border-dashed border-slate-300 p-6 text-slate-600 dark:border-zinc-700 dark:text-zinc-400 sm:col-span-2">No doctors are currently available.</p>
        @endforelse
    </div>
</div>
