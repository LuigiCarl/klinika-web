<?php

use App\Models\Clinic;
use App\ScheduleExceptionType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Scheduling configuration')] class extends Component {
    public Clinic $clinic;
    public string $clinicName = '';
    public string $clinicTimezone = '';
    public ?int $editingServiceId = null;
    public string $serviceName = '';
    public int $durationMinutes = 30;
    public ?int $editingPractitionerId = null;
    public string $practitionerName = '';
    public string $licenseNumber = '';
    public string $eligibilityPractitionerId = '';
    public string $eligibilityServiceId = '';
    public string $availabilityPractitionerId = '';
    public int $weekday = 1;
    public string $availabilityStartsAt = '09:00';
    public string $availabilityEndsAt = '17:00';
    public string $availabilityEffectiveFrom = '';
    public string $availabilityEffectiveUntil = '';
    public string $exceptionPractitionerId = '';
    public string $exceptionType = 'unavailable';
    public string $exceptionStartsAt = '';
    public string $exceptionEndsAt = '';
    public string $exceptionReason = '';

    public function mount(Clinic $clinic): void
    {
        Gate::authorize('configure', $clinic);
        $this->clinic = $clinic;
        $this->clinicName = $clinic->name;
        $this->clinicTimezone = $clinic->timezone;
    }

    #[Computed]
    public function services(): Collection
    {
        return $this->clinic->services()->orderBy('name')->get();
    }

    #[Computed]
    public function practitioners(): Collection
    {
        return $this->clinic->practitioners()->with('services')->orderBy('display_name')->get();
    }

    #[Computed]
    public function availabilityRules(): Collection
    {
        return $this->clinic->practitioners()->with(['availabilityRules' => fn ($query) => $query->orderBy('weekday')->orderBy('starts_at')])->orderBy('display_name')->get();
    }

    #[Computed]
    public function scheduleExceptions(): Collection
    {
        return $this->clinic->practitioners()->with(['scheduleExceptions' => fn ($query) => $query->orderBy('starts_at')])->orderBy('display_name')->get();
    }

    #[Computed]
    public function timezones(): array
    {
        return DateTimeZone::listIdentifiers();
    }

    public function updateClinic(): void
    {
        $this->authorizeConfiguration();
        $validated = $this->validate([
            'clinicName' => ['required', 'string', 'max:255'],
            'clinicTimezone' => ['required', 'timezone:all'],
        ]);
        $this->clinic->update(['name' => $validated['clinicName'], 'timezone' => $validated['clinicTimezone']]);
        $this->clinic->refresh();
    }

    public function createService(): void
    {
        $this->authorizeConfiguration();
        $validated = $this->validate([
            'serviceName' => ['required', 'string', 'max:255', Rule::unique('services', 'name')->where('clinic_id', $this->clinic->id)->ignore($this->editingServiceId)],
            'durationMinutes' => ['required', 'integer', 'min:5', 'max:480'],
        ]);
        $service = $this->editingServiceId === null
            ? $this->clinic->services()->make()
            : $this->clinic->services()->findOrFail($this->editingServiceId);
        $service->fill(['name' => $validated['serviceName'], 'duration_minutes' => $validated['durationMinutes']])->save();
        $this->reset('serviceName');
        $this->editingServiceId = null;
        $this->durationMinutes = 30;
        unset($this->services, $this->practitioners);
    }

    public function editService(int $serviceId): void
    {
        $this->authorizeConfiguration();
        $service = $this->clinic->services()->findOrFail($serviceId);
        $this->editingServiceId = $service->id;
        $this->serviceName = $service->name;
        $this->durationMinutes = $service->duration_minutes;
    }

    public function toggleService(int $serviceId): void
    {
        $this->authorizeConfiguration();
        $service = $this->clinic->services()->findOrFail($serviceId);
        $service->update(['is_active' => ! $service->is_active]);
        unset($this->services, $this->practitioners);
    }

    public function createPractitioner(): void
    {
        $this->authorizeConfiguration();
        $validated = $this->validate([
            'practitionerName' => ['required', 'string', 'max:255'],
            'licenseNumber' => ['nullable', 'string', 'max:255', Rule::unique('practitioners', 'license_number')->where('clinic_id', $this->clinic->id)->ignore($this->editingPractitionerId)],
        ]);
        $practitioner = $this->editingPractitionerId === null
            ? $this->clinic->practitioners()->make()
            : $this->clinic->practitioners()->findOrFail($this->editingPractitionerId);
        $practitioner->fill(['display_name' => $validated['practitionerName'], 'license_number' => $validated['licenseNumber'] ?: null])->save();
        $this->reset('practitionerName', 'licenseNumber');
        $this->editingPractitionerId = null;
        unset($this->practitioners, $this->availabilityRules, $this->scheduleExceptions);
    }

    public function editPractitioner(int $practitionerId): void
    {
        $this->authorizeConfiguration();
        $practitioner = $this->clinic->practitioners()->findOrFail($practitionerId);
        $this->editingPractitionerId = $practitioner->id;
        $this->practitionerName = $practitioner->display_name;
        $this->licenseNumber = $practitioner->license_number ?? '';
    }

    public function togglePractitioner(int $practitionerId): void
    {
        $this->authorizeConfiguration();
        $practitioner = $this->clinic->practitioners()->findOrFail($practitionerId);
        $practitioner->update(['is_active' => ! $practitioner->is_active]);
        unset($this->practitioners, $this->availabilityRules, $this->scheduleExceptions);
    }

    public function assignService(): void
    {
        $this->authorizeConfiguration();
        $validated = $this->validate([
            'eligibilityPractitionerId' => ['required', Rule::exists('practitioners', 'id')->where('clinic_id', $this->clinic->id)],
            'eligibilityServiceId' => ['required', Rule::exists('services', 'id')->where('clinic_id', $this->clinic->id)],
        ]);
        $practitioner = $this->clinic->practitioners()->findOrFail($validated['eligibilityPractitionerId']);
        $practitioner->services()->syncWithoutDetaching([$validated['eligibilityServiceId'] => ['clinic_id' => $this->clinic->id]]);
        unset($this->practitioners);
    }

    public function createAvailability(): void
    {
        $this->authorizeConfiguration();
        $validated = $this->validate([
            'availabilityPractitionerId' => ['required', Rule::exists('practitioners', 'id')->where('clinic_id', $this->clinic->id)],
            'weekday' => ['required', 'integer', 'between:1,7'],
            'availabilityStartsAt' => ['required', 'date_format:H:i'],
            'availabilityEndsAt' => ['required', 'date_format:H:i', 'after:availabilityStartsAt'],
            'availabilityEffectiveFrom' => ['nullable', 'date'],
            'availabilityEffectiveUntil' => ['nullable', 'date', 'after_or_equal:availabilityEffectiveFrom'],
        ]);
        $practitioner = $this->clinic->practitioners()->findOrFail($validated['availabilityPractitionerId']);
        $practitioner->availabilityRules()->create([
            'clinic_id' => $this->clinic->id,
            'weekday' => $validated['weekday'],
            'starts_at' => $validated['availabilityStartsAt'],
            'ends_at' => $validated['availabilityEndsAt'],
            'effective_from' => $validated['availabilityEffectiveFrom'] ?: null,
            'effective_until' => $validated['availabilityEffectiveUntil'] ?: null,
        ]);
        unset($this->availabilityRules);
    }

    public function removeServiceAssignment(int $practitionerId, int $serviceId): void
    {
        $this->authorizeConfiguration();
        $practitioner = $this->clinic->practitioners()->findOrFail($practitionerId);
        $service = $this->clinic->services()->findOrFail($serviceId);
        $practitioner->services()->detach($service);
        unset($this->practitioners);
    }

    public function deleteAvailability(int $practitionerId, int $availabilityId): void
    {
        $this->authorizeConfiguration();
        $practitioner = $this->clinic->practitioners()->findOrFail($practitionerId);
        $practitioner->availabilityRules()->whereKey($availabilityId)->firstOrFail()->delete();
        unset($this->availabilityRules);
    }

    public function createException(): void
    {
        $this->authorizeConfiguration();
        $validated = $this->validate([
            'exceptionPractitionerId' => ['required', Rule::exists('practitioners', 'id')->where('clinic_id', $this->clinic->id)],
            'exceptionType' => ['required', Rule::enum(ScheduleExceptionType::class)],
            'exceptionStartsAt' => ['required', 'date'],
            'exceptionEndsAt' => ['required', 'date', 'after:exceptionStartsAt'],
            'exceptionReason' => ['nullable', 'string', 'max:255'],
        ]);
        $practitioner = $this->clinic->practitioners()->findOrFail($validated['exceptionPractitionerId']);
        $practitioner->scheduleExceptions()->create([
            'clinic_id' => $this->clinic->id,
            'type' => $validated['exceptionType'],
            'starts_at' => CarbonImmutable::parse($validated['exceptionStartsAt'], $this->clinic->timezone)->utc(),
            'ends_at' => CarbonImmutable::parse($validated['exceptionEndsAt'], $this->clinic->timezone)->utc(),
            'reason' => $validated['exceptionReason'] ?: null,
        ]);
        $this->reset('exceptionStartsAt', 'exceptionEndsAt', 'exceptionReason');
        unset($this->scheduleExceptions);
    }

    public function deleteException(int $practitionerId, int $exceptionId): void
    {
        $this->authorizeConfiguration();
        $practitioner = $this->clinic->practitioners()->findOrFail($practitionerId);
        $practitioner->scheduleExceptions()->whereKey($exceptionId)->firstOrFail()->delete();
        unset($this->scheduleExceptions);
    }

    private function authorizeConfiguration(): void
    {
        Gate::authorize('configure', $this->clinic);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div><flux:heading size="xl">{{ $clinic->name }}</flux:heading><flux:text>{{ __('Scheduling configuration') }} · {{ $clinic->timezone }}</flux:text></div>
            <flux:button :href="route('clinics.index')" wire:navigate>{{ __('Back to clinics') }}</flux:button>
        </div>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading>{{ __('Clinic settings') }}</flux:heading>
            <form wire:submit="updateClinic" class="mt-4 grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                <flux:input wire:model="clinicName" :label="__('Clinic name')" required />
                <flux:select wire:model="clinicTimezone" :label="__('Timezone')" required>
                    @foreach ($this->timezones as $timezoneIdentifier)
                        <flux:select.option wire:key="clinic-timezone-{{ $timezoneIdentifier }}" :value="$timezoneIdentifier">{{ $timezoneIdentifier }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button type="submit" variant="primary">{{ __('Save clinic') }}</flux:button>
            </form>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading>{{ __('Services') }}</flux:heading>
                <form wire:submit="createService" class="mt-4 grid gap-4 sm:grid-cols-[1fr_10rem_auto] sm:items-end">
                    <flux:input wire:model="serviceName" :label="__('Service name')" required />
                    <flux:input wire:model="durationMinutes" :label="__('Minutes')" type="number" min="5" max="480" required />
                    <flux:button type="submit" variant="primary">{{ $editingServiceId ? __('Save') : __('Add') }}</flux:button>
                </form>
                <div class="mt-4 flex flex-wrap gap-2">@foreach ($this->services as $service)<span wire:key="service-{{ $service->id }}" class="rounded-full bg-zinc-100 px-3 py-1 text-sm dark:bg-zinc-800">{{ $service->name }} · {{ $service->duration_minutes }}m</span>@endforeach</div>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading>{{ __('Practitioners') }}</flux:heading>
                <form wire:submit="createPractitioner" class="mt-4 grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                    <flux:input wire:model="practitionerName" :label="__('Display name')" required />
                    <flux:input wire:model="licenseNumber" :label="__('License number')" />
                    <flux:button type="submit" variant="primary">{{ $editingPractitionerId ? __('Save') : __('Add') }}</flux:button>
                </form>
                <div class="mt-4 flex flex-col gap-2">@foreach ($this->practitioners as $practitioner)<div wire:key="practitioner-{{ $practitioner->id }}" class="rounded-lg bg-zinc-100 px-3 py-2 text-sm dark:bg-zinc-800"><span class="font-medium">{{ $practitioner->display_name }}</span> <span class="text-zinc-500">{{ $practitioner->services->pluck('name')->join(', ') ?: __('No services assigned') }}</span></div>@endforeach</div>
            </section>
        </div>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading>{{ __('Service eligibility') }}</flux:heading>
            <form wire:submit="assignService" class="mt-4 grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                <flux:select wire:model="eligibilityPractitionerId" :label="__('Practitioner')" required><flux:select.option value="">{{ __('Select practitioner') }}</flux:select.option>@foreach ($this->practitioners as $practitioner)<flux:select.option wire:key="ep-{{ $practitioner->id }}" :value="$practitioner->id">{{ $practitioner->display_name }}</flux:select.option>@endforeach</flux:select>
                <flux:select wire:model="eligibilityServiceId" :label="__('Service')" required><flux:select.option value="">{{ __('Select service') }}</flux:select.option>@foreach ($this->services as $service)<flux:select.option wire:key="es-{{ $service->id }}" :value="$service->id">{{ $service->name }}</flux:select.option>@endforeach</flux:select>
                <flux:button type="submit" variant="primary">{{ __('Assign') }}</flux:button>
            </form>
            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <div class="flex flex-col gap-2">
                    @foreach ($this->services as $service)
                        <div wire:key="manage-service-{{ $service->id }}" class="flex items-center justify-between gap-3 rounded-lg bg-zinc-100 px-3 py-2 text-sm dark:bg-zinc-800">
                            <span class="{{ $service->is_active ? '' : 'line-through opacity-60' }}">{{ $service->name }}</span>
                            <span class="flex gap-2"><flux:button size="xs" wire:click="editService({{ $service->id }})" wire:loading.attr="disabled">{{ __('Edit') }}</flux:button><flux:button size="xs" wire:click="toggleService({{ $service->id }})" wire:loading.attr="disabled">{{ $service->is_active ? __('Deactivate') : __('Activate') }}</flux:button></span>
                        </div>
                    @endforeach
                </div>
                <div class="flex flex-col gap-2">
                    @foreach ($this->practitioners as $practitioner)
                        <div wire:key="manage-practitioner-{{ $practitioner->id }}" class="rounded-lg bg-zinc-100 px-3 py-2 text-sm dark:bg-zinc-800">
                            <div class="flex items-center justify-between gap-3"><span class="{{ $practitioner->is_active ? '' : 'line-through opacity-60' }}">{{ $practitioner->display_name }}</span><span class="flex gap-2"><flux:button size="xs" wire:click="editPractitioner({{ $practitioner->id }})" wire:loading.attr="disabled">{{ __('Edit') }}</flux:button><flux:button size="xs" wire:click="togglePractitioner({{ $practitioner->id }})" wire:loading.attr="disabled">{{ $practitioner->is_active ? __('Deactivate') : __('Activate') }}</flux:button></span></div>
                            <div class="mt-2 flex flex-wrap gap-2">@foreach ($practitioner->services as $service)<span wire:key="assignment-{{ $practitioner->id }}-{{ $service->id }}" class="rounded-full bg-white px-2 py-1 dark:bg-zinc-900">{{ $service->name }} <button type="button" wire:click="removeServiceAssignment({{ $practitioner->id }}, {{ $service->id }})" wire:loading.attr="disabled" aria-label="{{ __('Remove service assignment') }}">×</button></span>@endforeach</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading>{{ __('Weekly availability') }}</flux:heading>
                <form wire:submit="createAvailability" class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="availabilityPractitionerId" :label="__('Practitioner')" required><flux:select.option value="">{{ __('Select practitioner') }}</flux:select.option>@foreach ($this->practitioners as $practitioner)<flux:select.option wire:key="ap-{{ $practitioner->id }}" :value="$practitioner->id">{{ $practitioner->display_name }}</flux:select.option>@endforeach</flux:select>
                    <flux:select wire:model="weekday" :label="__('Day')" required>@foreach ([1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'] as $number => $day)<flux:select.option :value="$number">{{ __($day) }}</flux:select.option>@endforeach</flux:select>
                    <flux:input wire:model="availabilityStartsAt" :label="__('Starts')" type="time" required />
                    <flux:input wire:model="availabilityEndsAt" :label="__('Ends')" type="time" required />
                    <flux:input wire:model="availabilityEffectiveFrom" :label="__('Effective from')" type="date" />
                    <flux:input wire:model="availabilityEffectiveUntil" :label="__('Effective until')" type="date" />
                    <flux:button type="submit" variant="primary" class="sm:col-span-2">{{ __('Add availability') }}</flux:button>
                </form>
                <div class="mt-4 flex flex-col gap-2">@foreach ($this->availabilityRules as $practitioner)@foreach ($practitioner->availabilityRules as $rule)<div wire:key="availability-{{ $rule->id }}" class="text-sm">{{ $practitioner->display_name }} · {{ ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][$rule->weekday] }} {{ substr($rule->starts_at, 0, 5) }}–{{ substr($rule->ends_at, 0, 5) }}</div>@endforeach @endforeach</div>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading>{{ __('Schedule exceptions') }}</flux:heading>
                <form wire:submit="createException" class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="exceptionPractitionerId" :label="__('Practitioner')" required><flux:select.option value="">{{ __('Select practitioner') }}</flux:select.option>@foreach ($this->practitioners as $practitioner)<flux:select.option wire:key="xp-{{ $practitioner->id }}" :value="$practitioner->id">{{ $practitioner->display_name }}</flux:select.option>@endforeach</flux:select>
                    <flux:select wire:model="exceptionType" :label="__('Type')" required><flux:select.option value="unavailable">{{ __('Unavailable') }}</flux:select.option><flux:select.option value="available">{{ __('Additional availability') }}</flux:select.option></flux:select>
                    <flux:input wire:model="exceptionStartsAt" :label="__('Starts in clinic timezone')" type="datetime-local" required />
                    <flux:input wire:model="exceptionEndsAt" :label="__('Ends in clinic timezone')" type="datetime-local" required />
                    <flux:input wire:model="exceptionReason" :label="__('Reason')" class="sm:col-span-2" />
                    <flux:button type="submit" variant="primary" class="sm:col-span-2">{{ __('Add exception') }}</flux:button>
                </form>
                <div class="mt-4 flex flex-col gap-2">@foreach ($this->scheduleExceptions as $practitioner)@foreach ($practitioner->scheduleExceptions as $exception)<div wire:key="exception-{{ $exception->id }}" class="text-sm">{{ $practitioner->display_name }} · {{ ucfirst($exception->type->value) }} · {{ $exception->starts_at->format('M j, Y H:i') }}</div>@endforeach @endforeach</div>
            </section>
        </div>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading>{{ __('Manage schedule records') }}</flux:heading>
            <div class="mt-4 grid gap-6 lg:grid-cols-2">
                <div class="flex flex-col gap-2">@foreach ($this->availabilityRules as $practitioner)@foreach ($practitioner->availabilityRules as $rule)<div wire:key="manage-availability-{{ $rule->id }}" class="flex items-center justify-between gap-3 text-sm"><span>{{ $practitioner->display_name }} · {{ ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][$rule->weekday] }} {{ substr($rule->starts_at, 0, 5) }}–{{ substr($rule->ends_at, 0, 5) }}</span><flux:button size="xs" wire:click="deleteAvailability({{ $practitioner->id }}, {{ $rule->id }})" wire:loading.attr="disabled">{{ __('Remove') }}</flux:button></div>@endforeach @endforeach</div>
                <div class="flex flex-col gap-2">@foreach ($this->scheduleExceptions as $practitioner)@foreach ($practitioner->scheduleExceptions as $exception)<div wire:key="manage-exception-{{ $exception->id }}" class="flex items-center justify-between gap-3 text-sm"><span>{{ $practitioner->display_name }} · {{ ucfirst($exception->type->value) }} · {{ $exception->starts_at->timezone($clinic->timezone)->format('M j, Y H:i') }}</span><flux:button size="xs" wire:click="deleteException({{ $practitioner->id }}, {{ $exception->id }})" wire:loading.attr="disabled">{{ __('Remove') }}</flux:button></div>@endforeach @endforeach</div>
            </div>
        </section>
</div>
