<?php

use App\Models\AdministrativeAudit;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\DoctorUnavailability;
use App\Models\Specialty;
use App\Services\DoctorScheduleService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Doctor management')] class extends Component {
    public string $specialtyName = '';
    public string $doctorName = '';
    public string $specialtyId = '';
    public string $durationMinutes = '30';
    public ?int $editingDoctorId = null;
    public string $editDoctorName = '';
    public string $editSpecialtyId = '';
    public string $editDurationMinutes = '30';
    public string $scheduleDoctorId = '';
    public string $weekday = '1';
    public string $startTime = '09:00';
    public string $endTime = '17:00';
    public string $effectiveFrom = '';
    public string $effectiveUntil = '';
    public string $unavailabilityDoctorId = '';
    public string $unavailableStartsAt = '';
    public string $unavailableEndsAt = '';
    public string $unavailabilityReason = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Doctor::class);
    }

    /** @return Collection<int, Specialty> */
    #[Computed]
    public function specialties(): Collection
    {
        return Specialty::query()->withCount('doctors')->orderBy('name')->get();
    }

    /** @return Collection<int, Doctor> */
    #[Computed]
    public function doctors(): Collection
    {
        return Doctor::query()
            ->with(['specialty', 'schedules' => fn ($query) => $query->orderBy('weekday')->orderBy('start_time'), 'unavailabilities' => fn ($query) => $query->orderBy('starts_at')])
            ->orderBy('name')
            ->get();
    }

    public function createSpecialty(): void
    {
        Gate::authorize('create', Specialty::class);
        $validated = $this->validate(['specialtyName' => ['required', 'string', 'max:255', Rule::unique('specialties', 'name')]]);

        DB::transaction(function () use ($validated): void {
            $specialty = Specialty::query()->create(['name' => $validated['specialtyName'], 'is_active' => true]);
            $this->audit($specialty, 'created', null, $specialty->getAttributes());
        });

        $this->reset('specialtyName');
        unset($this->specialties);
        Flux::toast(variant: 'success', text: __('Specialty created.'));
    }

    public function toggleSpecialty(int $specialtyId): void
    {
        $specialty = Specialty::query()->findOrFail($specialtyId);
        Gate::authorize('update', $specialty);

        if ($specialty->is_active && $specialty->doctors()->where('is_active', true)->exists()) {
            Flux::toast(variant: 'danger', text: __('Deactivate doctors in this specialty first.'));
            throw ValidationException::withMessages(['specialty' => 'Deactivate doctors in this specialty first.']);
        }

        $before = $specialty->getAttributes();
        $specialty->update(['is_active' => ! $specialty->is_active]);
        $this->audit($specialty, $specialty->is_active ? 'activated' : 'deactivated', $before, $specialty->getAttributes());
        unset($this->specialties);
        Flux::toast(
            variant: 'success',
            text: $specialty->fresh()->is_active ? __('Specialty activated.') : __('Specialty deactivated.')
        );
    }

    public function createDoctor(): void
    {
        Gate::authorize('create', Doctor::class);
        $validated = $this->validate([
            'doctorName' => ['required', 'string', 'max:255'],
            'specialtyId' => ['required', Rule::exists('specialties', 'id')->where('is_active', true)],
            'durationMinutes' => ['required', 'integer', Rule::in([15, 30, 45, 60])],
        ]);

        DB::transaction(function () use ($validated): void {
            $doctor = Doctor::query()->create([
                'name' => $validated['doctorName'],
                'specialty_id' => $validated['specialtyId'],
                'appointment_duration_minutes' => $validated['durationMinutes'],
                'is_active' => true,
            ]);
            $this->audit($doctor, 'created', null, $doctor->getAttributes());
        });

        $this->reset('doctorName', 'specialtyId');
        $this->durationMinutes = '30';
        unset($this->doctors, $this->specialties);
        Flux::toast(variant: 'success', text: __('Doctor created.'));
    }

    public function editDoctor(int $doctorId): void
    {
        $doctor = Doctor::query()->findOrFail($doctorId);
        Gate::authorize('update', $doctor);

        $this->editingDoctorId = $doctor->id;
        $this->editDoctorName = $doctor->name;
        $this->editSpecialtyId = (string) $doctor->specialty_id;
        $this->editDurationMinutes = (string) $doctor->appointment_duration_minutes;

        $this->modal('edit-doctor-modal')->show();
    }

    public function cancelEditDoctor(): void
    {
        $this->reset('editingDoctorId', 'editDoctorName', 'editSpecialtyId');
        $this->editDurationMinutes = '30';
        $this->modal('edit-doctor-modal')->close();
    }

    public function updateDoctor(): void
    {
        $doctor = Doctor::query()->findOrFail($this->editingDoctorId);
        Gate::authorize('update', $doctor);
        $validated = $this->validate([
            'editDoctorName' => ['required', 'string', 'max:255'],
            'editSpecialtyId' => ['required', Rule::exists('specialties', 'id')->where('is_active', true)],
            'editDurationMinutes' => ['required', 'integer', Rule::in([15, 30, 45, 60])],
        ]);
        $before = $doctor->getAttributes();
        $doctor->update([
            'name' => $validated['editDoctorName'],
            'specialty_id' => $validated['editSpecialtyId'],
            'appointment_duration_minutes' => $validated['editDurationMinutes'],
        ]);
        $this->audit($doctor, 'updated', $before, $doctor->getAttributes());
        $this->reset('editingDoctorId', 'editDoctorName', 'editSpecialtyId');
        $this->editDurationMinutes = '30';
        unset($this->doctors);
        $this->modal('edit-doctor-modal')->close();
        Flux::toast(variant: 'success', text: __('Doctor updated. Existing appointment durations were preserved.'));
    }

    public function toggleDoctor(int $doctorId, DoctorScheduleService $service): void
    {
        $doctor = Doctor::query()->findOrFail($doctorId);
        Gate::authorize('update', $doctor);

        try {
            $service->setDoctorActive($doctor, ! $doctor->is_active, Auth::user());
            unset($this->doctors);
            Flux::toast(
                variant: 'success',
                text: $doctor->fresh()->is_active ? __('Doctor activated.') : __('Doctor deactivated.')
            );
        } catch (ValidationException $e) {
            $message = $e->validator->errors()->first('doctor') ?? $e->getMessage();
            Flux::toast(variant: 'danger', text: $message);
            throw $e;
        }
    }

    public function createSchedule(DoctorScheduleService $service): void
    {
        $validated = $this->validate([
            'scheduleDoctorId' => ['required', 'exists:doctors,id'],
            'weekday' => ['required', 'integer', 'between:1,7'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i', 'after:startTime'],
            'effectiveFrom' => ['nullable', 'date'],
            'effectiveUntil' => ['nullable', 'date', 'after_or_equal:effectiveFrom'],
        ]);
        $doctor = Doctor::query()->findOrFail($validated['scheduleDoctorId']);
        Gate::authorize('update', $doctor);

        try {
            $service->createSchedule($doctor, [
                'weekday' => (int) $validated['weekday'],
                'start_time' => $validated['startTime'],
                'end_time' => $validated['endTime'],
                'effective_from' => $validated['effectiveFrom'] ?: null,
                'effective_until' => $validated['effectiveUntil'] ?: null,
            ], Auth::user());

            $this->reset('scheduleDoctorId', 'effectiveFrom', 'effectiveUntil');
            $this->weekday = '1';
            $this->startTime = '09:00';
            $this->endTime = '17:00';
            unset($this->doctors);
            Flux::toast(variant: 'success', text: __('Working interval added.'));
        } catch (ValidationException $e) {
            $message = $e->validator->errors()->first('schedule') ?? $e->getMessage();
            Flux::toast(variant: 'danger', text: $message);
            throw $e;
        }
    }

    public function deleteSchedule(int $scheduleId, DoctorScheduleService $service): void
    {
        $schedule = DoctorSchedule::query()->with('doctor')->findOrFail($scheduleId);
        Gate::authorize('update', $schedule->doctor);

        try {
            $service->deleteSchedule($schedule, Auth::user());
            unset($this->doctors);
            Flux::toast(variant: 'success', text: __('Working interval removed.'));
        } catch (ValidationException $e) {
            $message = $e->validator->errors()->first('schedule') ?? $e->getMessage();
            Flux::toast(variant: 'danger', text: $message);
            throw $e;
        }
    }

    public function createUnavailability(DoctorScheduleService $service): void
    {
        $validated = $this->validate([
            'unavailabilityDoctorId' => ['required', 'exists:doctors,id'],
            'unavailableStartsAt' => ['required', 'date'],
            'unavailableEndsAt' => ['required', 'date', 'after:unavailableStartsAt'],
            'unavailabilityReason' => ['required', 'string', 'max:255'],
        ]);
        $doctor = Doctor::query()->findOrFail($validated['unavailabilityDoctorId']);
        Gate::authorize('update', $doctor);

        try {
            $service->createUnavailability($doctor, [
                'starts_at' => $validated['unavailableStartsAt'],
                'ends_at' => $validated['unavailableEndsAt'],
                'reason' => $validated['unavailabilityReason'],
            ], Auth::user());

            $this->reset('unavailabilityDoctorId', 'unavailableStartsAt', 'unavailableEndsAt', 'unavailabilityReason');
            unset($this->doctors);
            Flux::toast(variant: 'success', text: __('Unavailability recorded.'));
        } catch (ValidationException $e) {
            $message = $e->validator->errors()->first('unavailability') ?? $e->getMessage();
            Flux::toast(variant: 'danger', text: $message);
            throw $e;
        }
    }

    public function deleteUnavailability(int $unavailabilityId, DoctorScheduleService $service): void
    {
        $unavailability = DoctorUnavailability::query()->with('doctor')->findOrFail($unavailabilityId);
        Gate::authorize('update', $unavailability->doctor);
        $service->deleteUnavailability($unavailability, Auth::user());
        unset($this->doctors);
        Flux::toast(variant: 'success', text: __('Unavailability removed.'));
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    private function audit(object $subject, string $action, ?array $before = null, ?array $after = null): void
    {
        AdministrativeAudit::query()->create([
            'actor_id' => Auth::id(),
            'auditable_type' => class_basename($subject),
            'auditable_id' => $subject->id,
            'action' => $action,
            'before' => $before,
            'after' => $after,
        ]);
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Doctors and schedules') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Manage specialties, doctor profiles, working intervals, breaks, and unavailability.') }}</flux:text>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Specialties') }}</flux:heading>
            <flux:error name="specialty" class="mt-2" />
            <form wire:submit="createSpecialty" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                <flux:input wire:model="specialtyName" :label="__('Specialty name')" required />
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Add specialty') }}</flux:button>
            </form>
            <div class="mt-5 flex flex-col gap-2">
                @forelse ($this->specialties as $specialty)
                    <div wire:key="specialty-{{ $specialty->id }}" class="flex items-center justify-between gap-3 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                        <div><div class="font-medium">{{ $specialty->name }}</div><div class="text-sm text-zinc-500">{{ $specialty->doctors_count }} {{ __('doctors') }}</div></div>
                        <flux:button size="sm" wire:click="toggleSpecialty({{ $specialty->id }})" wire:loading.attr="disabled">{{ $specialty->is_active ? __('Deactivate') : __('Activate') }}</flux:button>
                    </div>
                @empty
                    <flux:text>{{ __('No specialties yet.') }}</flux:text>
                @endforelse
            </div>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Add doctor') }}</flux:heading>
            <form wire:submit="createDoctor" class="mt-4 grid gap-3 sm:grid-cols-2">
                <flux:input wire:model="doctorName" :label="__('Doctor name')" required />
                <flux:select wire:model="specialtyId" :label="__('Specialty')" required>
                    <flux:select.option value="">{{ __('Select specialty') }}</flux:select.option>
                    @foreach ($this->specialties->where('is_active', true) as $specialty)
                        <flux:select.option wire:key="doctor-specialty-{{ $specialty->id }}" :value="$specialty->id">{{ $specialty->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="durationMinutes" :label="__('Consultation duration')" required>
                    @foreach ([15, 30, 45, 60] as $duration)
                        <flux:select.option wire:key="duration-{{ $duration }}" :value="$duration">{{ $duration }} {{ __('minutes') }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button type="submit" variant="primary" class="sm:self-end" wire:loading.attr="disabled">{{ __('Add doctor') }}</flux:button>
            </form>
        </section>
    </div>

    <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg">{{ __('Doctor directory') }}</flux:heading>
        <flux:error name="doctor" class="mt-2" />

        <flux:modal name="edit-doctor-modal" class="min-w-[22rem] max-w-lg space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit doctor') }}</flux:heading>
                <flux:subheading class="mt-1">{{ __('Update doctor details and consultation duration.') }}</flux:subheading>
            </div>

            <form wire:submit="updateDoctor" class="space-y-4">
                <flux:input wire:model="editDoctorName" :label="__('Doctor name')" required />
                <flux:select wire:model="editSpecialtyId" :label="__('Specialty')" required>
                    @foreach ($this->specialties->where('is_active', true) as $specialty)
                        <flux:select.option wire:key="edit-specialty-{{ $specialty->id }}" :value="$specialty->id">{{ $specialty->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="editDurationMinutes" :label="__('Consultation duration')" required>
                    @foreach ([15, 30, 45, 60] as $duration)
                        <flux:select.option wire:key="edit-duration-{{ $duration }}" :value="$duration">{{ $duration }} {{ __('minutes') }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex justify-end gap-3 pt-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            @forelse ($this->doctors as $doctor)
                <article wire:key="doctor-{{ $doctor->id }}" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold">{{ $doctor->name }}</h3>
                                @if (! $doctor->is_active)
                                    <flux:badge size="sm" color="zinc">{{ __('Inactive') }}</flux:badge>
                                @endif
                            </div>
                            <p class="text-sm text-zinc-500">{{ $doctor->specialty->name }} · {{ $doctor->appointment_duration_minutes }}m</p>
                        </div>
                        <div class="flex gap-2">
                            <flux:button size="sm" wire:click="editDoctor({{ $doctor->id }})">{{ __('Edit') }}</flux:button>
                            <flux:button size="sm" wire:click="toggleDoctor({{ $doctor->id }})" wire:loading.attr="disabled">{{ $doctor->is_active ? __('Deactivate') : __('Activate') }}</flux:button>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="text-sm font-medium">{{ __('Working intervals') }}</div>
                        <div class="mt-2 flex flex-col gap-2">
                            @forelse ($doctor->schedules as $schedule)
                                <div wire:key="schedule-{{ $schedule->id }}" class="flex items-center justify-between gap-3 text-sm">
                                    <span>{{ ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][$schedule->weekday] }} {{ substr($schedule->start_time, 0, 5) }}–{{ substr($schedule->end_time, 0, 5) }}</span>
                                    <button type="button" class="text-red-600 hover:underline dark:text-red-400" wire:click="deleteSchedule({{ $schedule->id }})">{{ __('Remove') }}</button>
                                </div>
                            @empty
                                <p class="text-sm text-zinc-500">{{ __('No working intervals. Gaps between intervals are treated as breaks.') }}</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="text-sm font-medium">{{ __('Unavailability') }}</div>
                        <div class="mt-2 flex flex-col gap-2">
                            @forelse ($doctor->unavailabilities as $unavailability)
                                <div wire:key="unavailability-{{ $unavailability->id }}" class="flex items-center justify-between gap-3 text-sm">
                                    <span>{{ $unavailability->starts_at->timezone('Asia/Manila')->format('M j, g:i A') }} · {{ $unavailability->reason }}</span>
                                    <button type="button" class="text-red-600 hover:underline dark:text-red-400" wire:click="deleteUnavailability({{ $unavailability->id }})">{{ __('Remove') }}</button>
                                </div>
                            @empty
                                <p class="text-sm text-zinc-500">{{ __('No unavailability recorded.') }}</p>
                            @endforelse
                        </div>
                    </div>
                </article>
            @empty
                <flux:text>{{ __('No doctors yet.') }}</flux:text>
            @endforelse
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Add working interval') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Create separate morning and afternoon intervals to represent a lunch break.') }}</flux:text>
            <flux:error name="schedule" class="mt-2" />
            <form wire:submit="createSchedule" class="mt-4 grid gap-3 sm:grid-cols-2">
                <flux:select wire:model="scheduleDoctorId" :label="__('Doctor')" required><flux:select.option value="">{{ __('Select doctor') }}</flux:select.option>@foreach ($this->doctors as $doctor)<flux:select.option wire:key="schedule-doctor-{{ $doctor->id }}" :value="$doctor->id">{{ $doctor->name }}</flux:select.option>@endforeach</flux:select>
                <flux:select wire:model="weekday" :label="__('Weekday')" required>@foreach ([1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'] as $value => $label)<flux:select.option wire:key="weekday-{{ $value }}" :value="$value">{{ __($label) }}</flux:select.option>@endforeach</flux:select>
                <flux:input wire:model="startTime" :label="__('Start')" type="time" step="900" required />
                <flux:input wire:model="endTime" :label="__('End')" type="time" step="900" required />
                <flux:input wire:model="effectiveFrom" :label="__('Effective from (optional)')" type="date" />
                <flux:input wire:model="effectiveUntil" :label="__('Effective until (optional)')" type="date" />
                <flux:button type="submit" variant="primary" class="sm:col-span-2" wire:loading.attr="disabled">{{ __('Add interval') }}</flux:button>
            </form>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Add unavailability') }}</flux:heading>
            <flux:error name="unavailability" class="mt-2" />
            <form wire:submit="createUnavailability" class="mt-4 grid gap-3 sm:grid-cols-2">
                <flux:select wire:model="unavailabilityDoctorId" :label="__('Doctor')" required><flux:select.option value="">{{ __('Select doctor') }}</flux:select.option>@foreach ($this->doctors as $doctor)<flux:select.option wire:key="unavailable-doctor-{{ $doctor->id }}" :value="$doctor->id">{{ $doctor->name }}</flux:select.option>@endforeach</flux:select>
                <flux:input wire:model="unavailabilityReason" :label="__('Reason')" required />
                <flux:input wire:model="unavailableStartsAt" :label="__('Starts')" type="datetime-local" required />
                <flux:input wire:model="unavailableEndsAt" :label="__('Ends')" type="datetime-local" required />
                <flux:button type="submit" variant="primary" class="sm:col-span-2" wire:loading.attr="disabled">{{ __('Add unavailability') }}</flux:button>
            </form>
        </section>
    </div>
</div>
