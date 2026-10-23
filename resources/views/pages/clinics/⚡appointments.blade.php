<?php

use App\AppointmentStatus;
use App\Models\Clinic;
use App\Role;
use App\Services\AppointmentScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Patients and appointments')] class extends Component {
    public Clinic $clinic;
    public string $patientSearch = '';
    public string $firstName = '';
    public string $lastName = '';
    public string $dateOfBirth = '';
    public string $mobile = '';
    public string $email = '';
    public bool $confirmDuplicate = false;
    public string $patientId = '';
    public string $practitionerId = '';
    public string $serviceId = '';
    public string $startsAt = '';
    public string $searchDate = '';
    /** @var list<string> */
    public array $availableSlots = [];
    public ?int $reschedulingAppointmentId = null;
    public string $rescheduleStartsAt = '';
    public string $cancellationReason = '';
    public string $bookingRequestKey = '';
    public string $rescheduleRequestKey = '';
    public bool $canManageAppointments = false;
    public bool $canViewAppointmentAudits = false;

    public function mount(Clinic $clinic): void
    {
        Gate::authorize('viewAppointments', $clinic);
        $this->clinic = $clinic;
        $this->bookingRequestKey = (string) Str::uuid();
        $this->rescheduleRequestKey = (string) Str::uuid();
        $this->canManageAppointments = Gate::allows('manageAppointments', $clinic);
        $this->canViewAppointmentAudits = Gate::allows('viewAppointmentAudits', $clinic);
    }

    #[Computed]
    public function patients(): Collection
    {
        return $this->clinic->patients()
            ->when($this->patientSearch !== '', function ($query): void {
                $search = '%'.addcslashes($this->patientSearch, '%_\\').'%';
                $query->where(fn ($query) => $query->where('first_name', 'like', $search)->orWhere('last_name', 'like', $search)->orWhere('mobile', 'like', $search)->orWhere('email', 'like', $search));
            })->orderBy('last_name')->orderBy('first_name')->limit(50)->get();
    }

    #[Computed]
    public function practitioners(): Collection
    {
        return $this->clinic->practitioners()->where('is_active', true)->orderBy('display_name')->get();
    }

    #[Computed]
    public function services(): Collection
    {
        return $this->clinic->services()->where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function appointments(): Collection
    {
        return $this->clinic->appointments()
            ->when(auth()->user()->hasClinicRole($this->clinic, Role::Practitioner), fn ($query) => $query->whereHas('practitioner', fn ($query) => $query->where('user_id', auth()->id())))
            ->with(['patient', 'practitioner', 'service'])
            ->when($this->canViewAppointmentAudits, fn ($query) => $query->with('audits.actor'))
            ->orderBy('starts_at')->limit(100)->get();
    }

    public function createPatient(): void
    {
        $this->authorizeManagement();
        $validated = $this->validate([
            'firstName' => ['required', 'string', 'max:255'], 'lastName' => ['required', 'string', 'max:255'],
            'dateOfBirth' => ['nullable', 'date', 'before_or_equal:today'], 'mobile' => ['nullable', 'string', 'max:40', 'required_without:email'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:mobile'], 'confirmDuplicate' => ['boolean'],
        ]);
        $normalizedMobile = $validated['mobile'] ? preg_replace('/\D+/', '', $validated['mobile']) : null;
        $normalizedEmail = $validated['email'] ? Str::lower(trim($validated['email'])) : null;
        $duplicateExists = $this->clinic->patients()->where(function ($query) use ($normalizedMobile, $normalizedEmail): void {
            $query->when($normalizedMobile, fn ($query) => $query->orWhere('normalized_mobile', $normalizedMobile))
                ->when($normalizedEmail, fn ($query) => $query->orWhere('normalized_email', $normalizedEmail));
        })->exists();
        if ($duplicateExists && ! $validated['confirmDuplicate']) {
            $this->addError('confirmDuplicate', 'Matching contact details already exist. Confirm to create a separate patient.');
            return;
        }
        $patient = $this->clinic->patients()->create([
            'first_name' => $validated['firstName'], 'last_name' => $validated['lastName'], 'date_of_birth' => $validated['dateOfBirth'] ?: null,
            'mobile' => $validated['mobile'] ?: null, 'email' => $validated['email'] ?: null,
        ]);
        $this->patientId = (string) $patient->id;
        $this->reset('firstName', 'lastName', 'dateOfBirth', 'mobile', 'email', 'confirmDuplicate');
        unset($this->patients);
    }

    public function book(AppointmentScheduler $scheduler): void
    {
        $this->authorizeManagement();
        $validated = $this->validate([
            'patientId' => ['required', Rule::exists('patients', 'id')->where('clinic_id', $this->clinic->id)],
            'practitionerId' => ['required', Rule::exists('practitioners', 'id')->where('clinic_id', $this->clinic->id)->where('is_active', true)],
            'serviceId' => ['required', Rule::exists('services', 'id')->where('clinic_id', $this->clinic->id)->where('is_active', true)], 'startsAt' => ['required', 'date'],
        ]);
        $scheduler->create($this->clinic, $this->clinic->patients()->findOrFail($validated['patientId']), $this->clinic->practitioners()->findOrFail($validated['practitionerId']), $this->clinic->services()->findOrFail($validated['serviceId']), CarbonImmutable::parse($validated['startsAt'], $this->clinic->timezone)->utc(), auth()->user(), $this->bookingRequestKey);
        $this->bookingRequestKey = (string) Str::uuid();
        $this->reset('startsAt');
        unset($this->appointments);
    }

    public function findAvailableSlots(AppointmentScheduler $scheduler): void
    {
        $this->authorizeManagement();
        $validated = $this->validate([
            'patientId' => ['required', Rule::exists('patients', 'id')->where('clinic_id', $this->clinic->id)],
            'practitionerId' => ['required', Rule::exists('practitioners', 'id')->where('clinic_id', $this->clinic->id)],
            'serviceId' => ['required', Rule::exists('services', 'id')->where('clinic_id', $this->clinic->id)],
            'searchDate' => ['required', 'date'],
        ]);
        $this->availableSlots = $scheduler->availableSlots(
            $this->clinic, $this->clinic->patients()->findOrFail($validated['patientId']),
            $this->clinic->practitioners()->findOrFail($validated['practitionerId']), $this->clinic->services()->findOrFail($validated['serviceId']),
            CarbonImmutable::parse($validated['searchDate'], $this->clinic->timezone),
        );
    }

    public function selectSlot(string $slot): void
    {
        $this->startsAt = $slot;
    }

    public function reschedule(AppointmentScheduler $scheduler): void
    {
        $this->authorizeManagement();
        $validated = $this->validate(['reschedulingAppointmentId' => ['required', 'integer'], 'rescheduleStartsAt' => ['required', 'date']]);
        $appointment = $this->clinic->appointments()->findOrFail($validated['reschedulingAppointmentId']);
        Gate::authorize('update', $appointment);
        $scheduler->reschedule($appointment, CarbonImmutable::parse($validated['rescheduleStartsAt'], $this->clinic->timezone)->utc(), auth()->user(), $this->rescheduleRequestKey);
        $this->rescheduleRequestKey = (string) Str::uuid();
        $this->reset('reschedulingAppointmentId', 'rescheduleStartsAt');
        unset($this->appointments);
    }

    public function transitionAppointment(int $appointmentId, string $status, AppointmentScheduler $scheduler): void
    {
        $this->authorizeManagement();
        $appointment = $this->clinic->appointments()->findOrFail($appointmentId);
        Gate::authorize('update', $appointment);
        $scheduler->transition($appointment, AppointmentStatus::from($status), auth()->user(), $this->cancellationReason ?: null);
        $this->reset('cancellationReason');
        unset($this->appointments);
    }

    private function authorizeManagement(): void
    {
        Gate::authorize('manageAppointments', $this->clinic);
    }
};
?>

<div class="flex w-full flex-1 flex-col gap-8">
    <div class="flex flex-wrap items-start justify-between gap-4"><div><flux:heading size="xl">{{ $clinic->name }}</flux:heading><flux:text>{{ __('Patients and appointments') }} · {{ $clinic->timezone }}</flux:text></div><flux:button :href="route('clinics.index')" wire:navigate>{{ __('Back') }}</flux:button></div>
    @can('manageAppointments', $clinic)
    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900"><flux:heading>{{ __('Add patient') }}</flux:heading><form wire:submit="createPatient" class="mt-4 grid gap-4 sm:grid-cols-2"><flux:input wire:model="firstName" :label="__('First name')" required /><flux:input wire:model="lastName" :label="__('Last name')" required /><flux:input wire:model="dateOfBirth" :label="__('Date of birth')" type="date" /><flux:input wire:model="mobile" :label="__('Mobile')" /><flux:input wire:model="email" :label="__('Email')" type="email" class="sm:col-span-2" /><flux:checkbox wire:model="confirmDuplicate" :label="__('Confirm separate record when contact details match')" class="sm:col-span-2" /><flux:button type="submit" variant="primary" class="sm:col-span-2" wire:loading.attr="disabled">{{ __('Add patient') }}</flux:button></form></section>
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900"><flux:heading>{{ __('Book appointment') }}</flux:heading><form wire:submit="book" class="mt-4 grid gap-4 sm:grid-cols-2"><flux:select wire:model="patientId" :label="__('Patient')" required><flux:select.option value="">{{ __('Select') }}</flux:select.option>@foreach ($this->patients as $patient)<flux:select.option wire:key="po-{{ $patient->id }}" :value="$patient->id">{{ $patient->full_name }}</flux:select.option>@endforeach</flux:select><flux:select wire:model="practitionerId" :label="__('Practitioner')" required><flux:select.option value="">{{ __('Select') }}</flux:select.option>@foreach ($this->practitioners as $practitioner)<flux:select.option wire:key="pro-{{ $practitioner->id }}" :value="$practitioner->id">{{ $practitioner->display_name }}</flux:select.option>@endforeach</flux:select><flux:select wire:model="serviceId" :label="__('Service')" required><flux:select.option value="">{{ __('Select') }}</flux:select.option>@foreach ($this->services as $service)<flux:select.option wire:key="so-{{ $service->id }}" :value="$service->id">{{ $service->name }} · {{ $service->duration_minutes }}m</flux:select.option>@endforeach</flux:select><div class="flex items-end gap-2"><flux:input wire:model="searchDate" :label="__('Find availability')" type="date" /><flux:button type="button" wire:click="findAvailableSlots" wire:loading.attr="disabled">{{ __('Find') }}</flux:button></div><div class="flex flex-wrap gap-2 sm:col-span-2">@foreach ($availableSlots as $slot)<flux:button size="xs" type="button" wire:key="slot-{{ $slot }}" wire:click="selectSlot('{{ $slot }}')">{{ CarbonImmutable::parse($slot, $clinic->timezone)->format('g:i A') }}</flux:button>@endforeach</div><flux:input wire:model="startsAt" :label="__('Start in clinic timezone')" type="datetime-local" step="900" required /><flux:button type="submit" variant="primary" class="sm:self-end" wire:loading.attr="disabled">{{ __('Book as pending') }}</flux:button></form></section>
    </div>
    <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900"><div class="flex flex-wrap items-end justify-between gap-4"><flux:heading>{{ __('Patient directory') }}</flux:heading><flux:input wire:model.live.debounce.300ms="patientSearch" :label="__('Search')" /></div><div class="mt-4 grid gap-2 md:grid-cols-2">@forelse ($this->patients as $patient)<div wire:key="patient-{{ $patient->id }}" class="rounded-lg bg-zinc-100 p-3 text-sm dark:bg-zinc-800"><div class="font-medium">{{ $patient->full_name }}</div><div class="text-zinc-500">{{ $patient->mobile ?: $patient->email }}</div></div>@empty<flux:text>{{ __('No patients found.') }}</flux:text>@endforelse</div></section>
    @endcan
    <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading>{{ __('Appointment calendar list') }}</flux:heading>
        <div class="mt-4 flex flex-col gap-3">
            @forelse ($this->appointments as $appointment)
                <article wire:key="appointment-{{ $appointment->id }}" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="flex flex-wrap justify-between gap-3">
                        <div>
                            <div class="font-medium">{{ $appointment->patient->full_name }} · {{ $appointment->service->name }}</div>
                            <div class="text-sm text-zinc-500">{{ $appointment->practitioner->display_name }} · {{ $appointment->starts_at->timezone($clinic->timezone)->format('M j, Y g:i A') }}–{{ $appointment->ends_at->timezone($clinic->timezone)->format('g:i A') }}</div>
                        </div>
                        <span class="rounded-full bg-zinc-100 px-3 py-1 text-sm dark:bg-zinc-800">{{ str($appointment->status->value)->headline() }}</span>
                    </div>
                    @if ($canManageAppointments)
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($appointment->status->allowedTransitions() as $nextStatus)
                                <flux:button size="xs" wire:click="transitionAppointment({{ $appointment->id }}, '{{ $nextStatus->value }}')" wire:loading.attr="disabled">{{ str($nextStatus->value)->headline() }}</flux:button>
                            @endforeach
                            @if ($appointment->status->blocksTime())
                                <flux:button size="xs" wire:click="$set('reschedulingAppointmentId', {{ $appointment->id }})">{{ __('Reschedule') }}</flux:button>
                            @endif
                        </div>
                        @if ($reschedulingAppointmentId === $appointment->id)
                            <form wire:submit="reschedule" class="mt-3 flex flex-wrap items-end gap-3">
                                <flux:input wire:model="rescheduleStartsAt" :label="__('New start')" type="datetime-local" step="900" required />
                                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Save') }}</flux:button>
                            </form>
                        @endif
                    @endif
                    @if ($canViewAppointmentAudits && $appointment->audits->isNotEmpty())
                        <details class="mt-3 text-sm">
                            <summary class="cursor-pointer">{{ __('Audit history') }}</summary>
                            <ul class="mt-2 flex flex-col gap-1">
                                @foreach ($appointment->audits as $audit)
                                    <li wire:key="audit-{{ $audit->id }}">{{ str($audit->action)->headline() }} · {{ $audit->actor?->name ?? __('System') }} · {{ $audit->created_at->timezone($clinic->timezone)->format('M j, Y g:i A') }}</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </article>
            @empty
                <flux:text>{{ __('No appointments found.') }}</flux:text>
            @endforelse
        </div>
    </section>
</div>
