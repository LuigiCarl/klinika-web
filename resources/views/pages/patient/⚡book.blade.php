<?php

use App\Exceptions\BookingConflictException;
use App\Models\ClinicSetting;
use App\Models\Doctor;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Book appointment')] class extends Component
{
    public Doctor $doctor;

    public string $date = '';

    public string $selectedSlot = '';

    public string $idempotencyKey = '';

    public int $step = 1;

    public function mount(Doctor $doctor, AvailabilityService $availability): void
    {
        abort_unless($doctor->is_active, 404);
        abort_unless(Auth::user()->patient !== null, 403, 'A patient profile is required.');
        $this->doctor = $doctor->load('specialty');
        $this->date = ($availability->firstAvailableDate($doctor, Auth::user()->patient)
            ?? CarbonImmutable::now('Asia/Manila')->addDay())->toDateString();
        $this->idempotencyKey = (string) Str::uuid();
    }

    /** @return list<CarbonImmutable> */
    #[Computed]
    public function availableSlots(): array
    {
        try {
            return app(AvailabilityService::class)->slots($this->doctor, CarbonImmutable::parse($this->date, 'Asia/Manila'), Auth::user()->patient);
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array{morning: list<CarbonImmutable>, afternoon: list<CarbonImmutable>} */
    #[Computed]
    public function groupedSlots(): array
    {
        $morning = [];
        $afternoon = [];

        foreach ($this->availableSlots as $slot) {
            if ($slot->timezone('Asia/Manila')->hour < 12) {
                $morning[] = $slot;
            } else {
                $afternoon[] = $slot;
            }
        }

        return [
            'morning' => $morning,
            'afternoon' => $afternoon,
        ];
    }

    /** @return list<array{date: string, label: string, slots_count: int}> */
    #[Computed]
    public function upcomingDates(): array
    {
        try {
            $settings = ClinicSetting::query()->find(1);
            $timezone = $settings->timezone ?? 'Asia/Manila';
            $windowDays = $settings->booking_window_days ?? 30;
            $availability = app(AvailabilityService::class);
            $patient = Auth::user()->patient;

            $cursor = CarbonImmutable::now($timezone)->startOfDay();
            $lastDate = $cursor->addDays($windowDays);
            $dates = [];

            while ($cursor->lessThanOrEqualTo($lastDate) && count($dates) < 5) {
                $slots = $availability->slots($this->doctor, $cursor, $patient);
                if ($slots !== []) {
                    $dates[] = [
                        'date' => $cursor->toDateString(),
                        'label' => $cursor->format('D, M j'),
                        'slots_count' => count($slots),
                    ];
                }
                $cursor = $cursor->addDay();
            }

            return $dates;
        } catch (Throwable) {
            return [];
        }
    }

    public function selectQuickDate(string $selectedDate): void
    {
        $this->date = $selectedDate;
        $this->updatedDate();
    }

    public function updatedDate(): void
    {
        $this->selectedSlot = '';
        $this->step = 2;
        unset($this->availableSlots, $this->groupedSlots);
    }

    public function updatedSelectedSlot(): void
    {
        if ($this->selectedSlot !== '') {
            $this->step = 3;
        }
    }

    public function goToStep(int $targetStep): void
    {
        if ($targetStep === 3 && $this->selectedSlot === '') {
            return;
        }

        if ($targetStep >= 1 && $targetStep <= 3) {
            $this->step = $targetStep;
        }
    }

    public function book(BookingService $booking): void
    {
        $validated = $this->validate([
            'selectedSlot' => ['required', 'date'],
            'idempotencyKey' => ['required', 'uuid'],
        ], ['selectedSlot.required' => 'Please select an available appointment time.']);

        try {
            $appointment = $booking->book(
                Auth::user()->patient,
                $this->doctor,
                CarbonImmutable::parse($validated['selectedSlot'])->utc(),
                $validated['idempotencyKey'],
            );
        } catch (BookingConflictException $exception) {
            $this->addError('selectedSlot', $exception->getMessage());
            unset($this->availableSlots, $this->groupedSlots);

            return;
        } catch (ValidationException $exception) {
            throw $exception;
        }

        $this->redirectRoute('patient.appointments.confirmation', $appointment, navigate: true);
    }
}; ?>

<div class="mx-auto max-w-4xl space-y-6">
    <div>
        <flux:link :href="route('doctors.show', $doctor)" wire:navigate>&larr; Doctor details</flux:link>
        <flux:heading size="xl" class="mt-4">Request an appointment</flux:heading>
        <flux:text class="mt-1">{{ $doctor->name }} · {{ $doctor->specialty->name }} · {{ $doctor->appointment_duration_minutes }}-minute consultation</flux:text>
    </div>

    <nav aria-label="{{ __('Booking progress') }}" class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <ol class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            @foreach ([
                1 => ['title' => __('1. Select Date'), 'subtitle' => $date ? CarbonImmutable::parse($date, 'Asia/Manila')->format('M j, Y') : __('Choose day')],
                2 => ['title' => __('2. Choose Time Slot'), 'subtitle' => $selectedSlot ? CarbonImmutable::parse($selectedSlot)->timezone('Asia/Manila')->format('g:i A') : __('15-min interval')],
                3 => ['title' => __('3. Confirm Request'), 'subtitle' => __('Review & submit')],
            ] as $stepNumber => $meta)
                <li wire:key="stepper-{{ $stepNumber }}">
                    <button
                        type="button"
                        wire:click="goToStep({{ $stepNumber }})"
                        @disabled($stepNumber === 3 && $selectedSlot === '')
                        class="flex w-full items-center gap-3 rounded-lg p-1.5 text-left transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60 dark:hover:bg-zinc-800/60"
                    >
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $step >= $stepNumber ? 'bg-teal-600 text-white' : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' }}">
                            {{ $stepNumber }}
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $meta['title'] }}</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $meta['subtitle'] }}</p>
                        </div>
                    </button>
                </li>
            @endforeach
        </ol>
    </nav>

    <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <form wire:submit="book" class="space-y-6">
            <div class="space-y-3">
                @if ($this->upcomingDates !== [])
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Next available dates') }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($this->upcomingDates as $upcoming)
                                <button
                                    type="button"
                                    wire:key="quick-date-{{ $upcoming['date'] }}"
                                    wire:click="selectQuickDate('{{ $upcoming['date'] }}')"
                                    class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition {{ $date === $upcoming['date'] ? 'border-teal-600 bg-teal-50 text-teal-800 dark:border-teal-500 dark:bg-teal-950/60 dark:text-teal-200' : 'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700' }}"
                                >
                                    <span>{{ $upcoming['label'] }}</span>
                                    <span class="rounded-full bg-zinc-100 px-1.5 py-0.5 text-[10px] text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">{{ $upcoming['slots_count'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <flux:input wire:model.live="date" type="date" :label="__('Appointment date')" required />
            </div>

            <fieldset class="space-y-4">
                <legend class="text-sm font-medium text-zinc-900 dark:text-white">Available times</legend>

                @if ($this->availableSlots !== [])
                    @if ($this->groupedSlots['morning'] !== [])
                        <div class="space-y-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                {{ __('Morning slots') }} ({{ count($this->groupedSlots['morning']) }})
                            </p>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                                @foreach ($this->groupedSlots['morning'] as $appointmentSlot)
                                    <label wire:key="slot-{{ $appointmentSlot->timestamp }}" class="cursor-pointer">
                                        <input class="peer sr-only" type="radio" wire:model.live="selectedSlot" value="{{ $appointmentSlot->toIso8601String() }}">
                                        <span class="block rounded-lg border border-zinc-300 px-3 py-2 text-center text-sm transition peer-checked:border-teal-600 peer-checked:bg-teal-50 peer-checked:font-semibold peer-checked:text-teal-800 dark:border-zinc-600 dark:peer-checked:bg-teal-950 dark:peer-checked:text-teal-200">
                                            {{ $appointmentSlot->timezone('Asia/Manila')->format('g:i A') }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($this->groupedSlots['afternoon'] !== [])
                        <div class="space-y-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                {{ __('Afternoon slots') }} ({{ count($this->groupedSlots['afternoon']) }})
                            </p>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                                @foreach ($this->groupedSlots['afternoon'] as $appointmentSlot)
                                    <label wire:key="slot-{{ $appointmentSlot->timestamp }}" class="cursor-pointer">
                                        <input class="peer sr-only" type="radio" wire:model.live="selectedSlot" value="{{ $appointmentSlot->toIso8601String() }}">
                                        <span class="block rounded-lg border border-zinc-300 px-3 py-2 text-center text-sm transition peer-checked:border-teal-600 peer-checked:bg-teal-50 peer-checked:font-semibold peer-checked:text-teal-800 dark:border-zinc-600 dark:peer-checked:bg-teal-950 dark:peer-checked:text-teal-200">
                                            {{ $appointmentSlot->timezone('Asia/Manila')->format('g:i A') }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @else
                    <p class="rounded-lg bg-zinc-50 p-4 text-sm text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">No available slots for this date. Try another day.</p>
                @endif

                <flux:error name="selectedSlot" />
            </fieldset>

            <flux:error name="rate_limit" />

            @if ($selectedSlot !== '')
                @php
                    $selectedStartLocal = CarbonImmutable::parse($selectedSlot)->timezone('Asia/Manila');
                    $selectedEndLocal = $selectedStartLocal->addMinutes($doctor->appointment_duration_minutes);
                @endphp
                <div class="rounded-xl border border-teal-200 bg-teal-50/60 p-4 text-sm text-teal-950 dark:border-teal-800 dark:bg-teal-950/30 dark:text-teal-200">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold">{{ __('Selected Appointment Summary') }}</p>
                            <p class="mt-1">
                                {{ $doctor->name }} ({{ $doctor->specialty->name }}) · {{ $selectedStartLocal->format('F j, Y \a\t g:i A') }} – {{ $selectedEndLocal->format('g:i A') }} ({{ $doctor->appointment_duration_minutes }} {{ __('mins') }})
                            </p>
                        </div>
                        <button
                            type="button"
                            wire:click="goToStep(2)"
                            class="shrink-0 text-xs font-semibold text-teal-700 underline hover:text-teal-900 dark:text-teal-300 dark:hover:text-teal-100"
                        >
                            {{ __('Change time') }}
                        </button>
                    </div>
                </div>
            @endif

            <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">Requests remain pending until clinic staff review them. Cancelling later does not guarantee that another slot will be available.</div>
            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="book">Submit appointment request</flux:button>
                <span wire:loading wire:target="book" role="status" class="text-sm text-zinc-500">Submitting request...</span>
            </div>
        </form>
    </div>
</div>
