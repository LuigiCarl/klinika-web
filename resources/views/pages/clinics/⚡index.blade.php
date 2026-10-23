<?php

use App\Models\Clinic;
use App\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Clinics')] class extends Component {
    public string $name = '';
    public string $timezone = 'Asia/Manila';

    public function mount(): void
    {
        Gate::authorize('viewAny', Clinic::class);
    }

    #[Computed]
    public function clinics()
    {
        return Clinic::query()
            ->whereHas('users', fn ($query) => $query
                ->whereKey(Auth::id())
                ->whereIn('clinic_user.role', [Role::Administrator->value, Role::Receptionist->value, Role::Practitioner->value]))
            ->withCount(['practitioners', 'services'])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function timezones(): array
    {
        return DateTimeZone::listIdentifiers();
    }

    public function createClinic(): void
    {
        Gate::authorize('create', Clinic::class);
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'timezone:all'],
        ]);

        DB::transaction(function () use ($validated): void {
            $baseSlug = Str::slug($validated['name']);
            $slug = $baseSlug;
            $suffix = 2;

            while (Clinic::query()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix++;
            }

            $clinic = Clinic::query()->create([...$validated, 'slug' => $slug]);
            $clinic->users()->attach(Auth::id(), ['role' => Role::Administrator->value]);
        });

        $this->reset('name');
        unset($this->clinics);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('Clinics') }}</flux:heading>
            <flux:text>{{ __('Create and select a clinic to configure its services and schedules.') }}</flux:text>
        </div>

        @can('create', App\Models\Clinic::class)
        <form wire:submit="createClinic" class="grid gap-4 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900 md:grid-cols-[1fr_1fr_auto] md:items-end">
            <flux:input wire:model="name" :label="__('Clinic name')" required />
            <flux:select wire:model="timezone" :label="__('Timezone')" required>
                @foreach ($this->timezones as $timezoneIdentifier)
                    <flux:select.option wire:key="timezone-{{ $timezoneIdentifier }}" :value="$timezoneIdentifier">{{ $timezoneIdentifier }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Create clinic') }}</flux:button>
        </form>
        @endcan

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($this->clinics as $clinic)
                <div wire:key="clinic-{{ $clinic->id }}" class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading>{{ $clinic->name }}</flux:heading>
                    <flux:text class="mt-1">{{ $clinic->timezone }}</flux:text>
                    <div class="mt-4 flex gap-4 text-sm text-zinc-600 dark:text-zinc-300">
                        <span>{{ $clinic->practitioners_count }} {{ __('practitioners') }}</span>
                        <span>{{ $clinic->services_count }} {{ __('services') }}</span>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <flux:button size="sm" :href="route('clinics.appointments', $clinic)" wire:navigate>{{ __('Patients & appointments') }}</flux:button>
                        @can('configure', $clinic)<flux:button size="sm" :href="route('clinics.scheduling', $clinic)" wire:navigate>{{ __('Configure') }}</flux:button>@endcan
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700 md:col-span-2 xl:col-span-3">
                    <flux:text>{{ __('No clinics yet.') }}</flux:text>
                </div>
            @endforelse
        </div>
</div>
