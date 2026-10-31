<?php

use App\Exceptions\BookingConflictException;
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

    public function updatedDate(): void
    {
        $this->selectedSlot = '';
        $this->step = 2;
        unset($this->availableSlots);
    }

    public function updatedSelectedSlot(): void
    {
        if ($this->selectedSlot !== '') {
            $this->step = 3;
        }
    }

    public function goToStep(int $targetStep): void
    {
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
            unset($this->availableSlots);

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
        <flux:text class="mt-1">{{ $doctor->name }} · {{ $doctor->specialty->name }}</flux:text>
    </div>

    <nav aria-label="{{ __('Booking progress') }}" class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <ol class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            @foreach ([
                1 => ['title' => __('1. Select Date'), 'subtitle' => $date ? CarbonImmutable::parse($date, 'Asia/Manila')->format('M j, Y') : __('Choose day')],
                2 => ['title' => __('2. Choose Time Slot'), 'subtitle' => $selectedSlot ? CarbonImmutable::parse($selectedSlot)->timezone('Asia/Manila')->format('g:i A') : __('15-min interval')],
                3 => ['title' => __('3. Confirm Request'), 'subtitle' => __('Review & submit')],
            ] as $stepNumber => $meta)
                <li wire:key="stepper-{{ $stepNumber }}" class="flex items-center gap-3">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $step >= $stepNumber ? 'bg-teal-600 text-white' : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' }}">
                        {{ $stepNumber }}
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $meta['title'] }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $meta['subtitle'] }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </nav>

    <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <form wire:submit="book" class="space-y-5">
            <flux:input wire:model.live="date" type="date" :label="__('Appointment date')" required />
            <fieldset>
                <legend class="text-sm font-medium">Available times</legend>
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                    @forelse ($this->availableSlots as $appointmentSlot)
                        <label wire:key="slot-{{ $appointmentSlot->timestamp }}" class="cursor-pointer">
                            <input class="peer sr-only" type="radio" wire:model.live="selectedSlot" value="{{ $appointmentSlot->toIso8601String() }}">
                            <span class="block rounded-lg border border-zinc-300 px-3 py-2 text-center text-sm peer-checked:border-teal-600 peer-checked:bg-teal-50 peer-checked:text-teal-800 dark:border-zinc-600 dark:peer-checked:bg-teal-950">{{ $appointmentSlot->timezone('Asia/Manila')->format('g:i A') }}</span>
                        </label>
                    @empty
                        <p class="col-span-full rounded-lg bg-zinc-50 p-4 text-sm text-zinc-600 dark:bg-zinc-800">No available slots for this date. Try another day.</p>
                    @endforelse
                </div>
                <flux:error name="selectedSlot" />
            </fieldset>
            <flux:error name="rate_limit" />

            @if ($selectedSlot !== '')
                <div class="rounded-xl border border-teal-200 bg-teal-50/60 p-4 text-sm text-teal-950 dark:border-teal-800 dark:bg-teal-950/30 dark:text-teal-200">
                    <p class="font-semibold">{{ __('Selected Appointment Summary') }}</p>
                    <p class="mt-1">
                        {{ $doctor->name }} ({{ $doctor->specialty->name }}) · {{ CarbonImmutable::parse($selectedSlot)->timezone('Asia/Manila')->format('F j, Y \a\t g:i A') }} ({{ $doctor->appointment_duration_minutes }} {{ __('mins') }})
                    </p>
                </div>
            @endif

            <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-900">Requests remain pending until clinic staff review them. Cancelling later does not guarantee that another slot will be available.</div>
            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="book">Submit appointment request</flux:button>
                <span wire:loading wire:target="book" role="status" class="text-sm text-zinc-500">Submitting request...</span>
            </div>
        </form>
    </div>
</div>
