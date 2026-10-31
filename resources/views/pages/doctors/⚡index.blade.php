<?php

use App\Models\Doctor;
use App\Models\Specialty;
use App\Role;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.public'), Title('Our doctors')] class extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public string $specialty = '';

    #[Url]
    public string $weekday = '';

    #[Url]
    public string $duration = '';

    /** @return Collection<int, Specialty> */
    #[Computed]
    public function specialties(): Collection
    {
        return Specialty::query()->where('is_active', true)->orderBy('name')->get();
    }

    /** @return array<int, string> */
    #[Computed]
    public function weekdayOptions(): array
    {
        return [
            1 => __('Monday'),
            2 => __('Tuesday'),
            3 => __('Wednesday'),
            4 => __('Thursday'),
            5 => __('Friday'),
            6 => __('Saturday'),
            7 => __('Sunday'),
        ];
    }

    /** @return Collection<int, Doctor> */
    #[Computed]
    public function doctors(): Collection
    {
        return Doctor::query()
            ->with([
                'specialty',
                'schedules' => fn ($query) => $query->orderBy('weekday')->orderBy('start_time'),
            ])
            ->where('is_active', true)
            ->whereHas('specialty', fn ($query) => $query->where('is_active', true))
            ->when($this->search !== '', function ($query): void {
                $term = '%'.mb_strtolower($this->search).'%';
                $query->where(function ($subQuery) use ($term): void {
                    $subQuery->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereHas('specialty', fn ($sq) => $sq->whereRaw('LOWER(name) LIKE ?', [$term]));
                });
            })
            ->when($this->specialty !== '', fn ($query) => $query->where('specialty_id', (int) $this->specialty))
            ->when($this->weekday !== '', fn ($query) => $query->whereHas('schedules', fn ($sq) => $sq->where('weekday', (int) $this->weekday)))
            ->when($this->duration !== '', fn ($query) => $query->where('appointment_duration_minutes', (int) $this->duration))
            ->orderBy('name')
            ->get();
    }

    /** @return array<int, string|null> */
    #[Computed]
    public function nextAvailableDates(): array
    {
        $availability = app(AvailabilityService::class);
        $dates = [];

        foreach ($this->doctors as $doctor) {
            try {
                $nextDate = $availability->firstAvailableDate($doctor);
                $dates[$doctor->id] = $nextDate?->format('M j, Y');
            } catch (Throwable) {
                $dates[$doctor->id] = null;
            }
        }

        return $dates;
    }

    public function selectSpecialty(string $specialtyId): void
    {
        $this->specialty = $this->specialty === $specialtyId ? '' : $specialtyId;
        unset($this->doctors, $this->nextAvailableDates);
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'specialty', 'weekday', 'duration');
        unset($this->doctors, $this->nextAvailableDates);
    }
}; ?>

<div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="max-w-2xl">
        <p class="font-semibold text-teal-700 dark:text-teal-400">Single-clinic care</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-4xl">Choose your doctor</h1>
        <p class="mt-3 text-slate-600 dark:text-zinc-400">Review active doctors, their specialties, and their regular clinic schedules.</p>
    </div>

    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="sm:col-span-2 lg:col-span-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search by doctor name or specialty...')"
                :label="__('Search directory')"
            />
        </div>
        <div>
            <flux:select wire:model.live="specialty" :label="__('Filter by specialty')">
                <flux:select.option value="">{{ __('All specialties') }}</flux:select.option>
                @foreach ($this->specialties as $spec)
                    <flux:select.option wire:key="filter-spec-{{ $spec->id }}" :value="$spec->id">{{ $spec->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div>
            <flux:select wire:model.live="weekday" :label="__('Available day')">
                <flux:select.option value="">{{ __('Any day of week') }}</flux:select.option>
                @foreach ($this->weekdayOptions as $dayNumber => $dayLabel)
                    <flux:select.option wire:key="filter-day-{{ $dayNumber }}" :value="$dayNumber">{{ $dayLabel }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div>
            <flux:select wire:model.live="duration" :label="__('Consultation length')">
                <flux:select.option value="">{{ __('Any duration') }}</flux:select.option>
                @foreach ([15, 30, 45, 60] as $mins)
                    <flux:select.option wire:key="filter-duration-{{ $mins }}" :value="$mins">{{ $mins }} {{ __('minutes') }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    @if ($this->specialties->isNotEmpty())
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">{{ __('Quick specialty:') }}</span>
            <button
                type="button"
                wire:click="selectSpecialty('')"
                class="rounded-full px-3 py-1 text-xs font-semibold transition {{ $specialty === '' ? 'bg-teal-700 text-white dark:bg-teal-600' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}"
            >
                {{ __('All') }}
            </button>
            @foreach ($this->specialties as $spec)
                <button
                    type="button"
                    wire:key="pill-spec-{{ $spec->id }}"
                    wire:click="selectSpecialty('{{ $spec->id }}')"
                    class="rounded-full px-3 py-1 text-xs font-semibold transition {{ $specialty === (string) $spec->id ? 'bg-teal-700 text-white dark:bg-teal-600' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}"
                >
                    {{ $spec->name }}
                </button>
            @endforeach

            @if ($search !== '' || $specialty !== '' || $weekday !== '' || $duration !== '')
                <flux:button wire:click="clearFilters" size="sm" variant="subtle">{{ __('Clear filters') }}</flux:button>
            @endif
        </div>
    @elseif ($search !== '' || $specialty !== '' || $weekday !== '' || $duration !== '')
        <div class="mt-4">
            <flux:button wire:click="clearFilters" size="sm" variant="subtle">{{ __('Clear filters') }}</flux:button>
        </div>
    @endif

    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->doctors as $doctor)
            <article wire:key="doctor-{{ $doctor->id }}" class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition-colors dark:border-zinc-700 dark:bg-zinc-800">
                <div>
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-semibold text-teal-700 dark:text-teal-400">{{ $doctor->specialty->name }}</p>
                        @if (! empty($this->nextAvailableDates[$doctor->id]))
                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                                {{ __('Next available:') }} {{ $this->nextAvailableDates[$doctor->id] }}
                            </span>
                        @endif
                    </div>

                    <h2 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">{{ $doctor->name }}</h2>
                    <p class="mt-2 text-sm text-slate-600 dark:text-zinc-400">{{ $doctor->appointment_duration_minutes }}-minute appointments</p>

                    @if ($doctor->schedules->isNotEmpty())
                        <div class="mt-4 flex flex-wrap gap-1.5">
                            @foreach ($doctor->schedules->pluck('weekday')->unique() as $dayNum)
                                <span wire:key="doc-{{ $doctor->id }}-day-{{ $dayNum }}" class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-zinc-700 dark:text-zinc-200">
                                    {{ CarbonImmutable::now()->startOfWeek()->addDays($dayNum - 1)->format('D') }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="mt-6 flex items-center justify-between gap-3 border-t border-slate-100 pt-4 dark:border-zinc-700/70">
                    <a href="{{ route('doctors.show', $doctor) }}" class="inline-flex text-sm font-semibold text-teal-700 hover:text-teal-900 dark:text-teal-400 dark:hover:text-teal-300" wire:navigate>View doctor &rarr;</a>
                    @auth
                        @if (auth()->user()->hasRole(Role::Patient))
                            <a href="{{ route('patient.book', $doctor) }}" class="inline-flex items-center rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-800 dark:bg-teal-600 dark:hover:bg-teal-500" wire:navigate>
                                {{ __('Book now') }}
                            </a>
                        @endif
                    @endauth
                </div>
            </article>
        @empty
            <p class="rounded-xl border border-dashed border-slate-300 p-6 text-slate-600 dark:border-zinc-700 dark:text-zinc-400 sm:col-span-2 lg:col-span-3">No doctors are currently available.</p>
        @endforelse
    </div>
</div>
