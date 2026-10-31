<?php

use App\Models\AdministrativeAudit;
use App\Models\AppointmentEvent;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Audit History')] class extends Component
{
    #[Url]
    public string $tab = 'admin';

    #[Url]
    public string $search = '';

    /** @return Collection<int, AdministrativeAudit> */
    #[Computed]
    public function adminAudits(): Collection
    {
        return AdministrativeAudit::query()
            ->with('actor')
            ->when($this->search !== '', function ($query): void {
                $term = '%'.mb_strtolower($this->search).'%';
                $query->where(function ($subQuery) use ($term): void {
                    $subQuery->whereRaw('LOWER(action) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(auditable_type) LIKE ?', [$term]);
                });
            })
            ->latest('created_at')
            ->limit(100)
            ->get();
    }

    /** @return Collection<int, AppointmentEvent> */
    #[Computed]
    public function appointmentEvents(): Collection
    {
        return AppointmentEvent::query()
            ->with(['actor', 'appointment.patient.user', 'appointment.doctor'])
            ->when($this->search !== '', function ($query): void {
                $term = '%'.mb_strtolower($this->search).'%';
                $query->where(function ($subQuery) use ($term): void {
                    $subQuery->whereRaw('LOWER(event_type) LIKE ?', [$term])
                        ->orWhereHas('appointment', fn ($aq) => $aq->whereRaw('LOWER(reference_code) LIKE ?', [$term]));
                });
            })
            ->latest('created_at')
            ->limit(100)
            ->get();
    }
};
?>

<div class="flex w-full flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('System Audit History') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Inspect immutable administrative changes and clinical appointment lifecycle events.') }}</flux:text>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('Filter by action or reference...')"
            />
            <div class="inline-flex rounded-lg border border-zinc-200 bg-zinc-50 p-1 dark:border-zinc-700 dark:bg-zinc-800">
                <button
                    type="button"
                    wire:click="$set('tab', 'admin')"
                    class="rounded-md px-3 py-1.5 text-xs font-semibold transition {{ $tab === 'admin' ? 'bg-white text-zinc-900 shadow-xs dark:bg-zinc-900 dark:text-white' : 'text-zinc-600 dark:text-zinc-400' }}"
                >
                    {{ __('Administrative Audits') }}
                </button>
                <button
                    type="button"
                    wire:click="$set('tab', 'appointments')"
                    class="rounded-md px-3 py-1.5 text-xs font-semibold transition {{ $tab === 'appointments' ? 'bg-white text-zinc-900 shadow-xs dark:bg-zinc-900 dark:text-white' : 'text-zinc-600 dark:text-zinc-400' }}"
                >
                    {{ __('Appointment Events') }}
                </button>
            </div>
        </div>
    </div>

    @if ($tab === 'admin')
        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->adminAudits as $audit)
                    <article wire:key="admin-audit-{{ $audit->id }}" class="p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <span class="rounded-md bg-teal-50 px-2 py-0.5 text-xs font-bold uppercase text-teal-700 dark:bg-teal-950 dark:text-teal-300">
                                    {{ str_replace('_', ' ', $audit->action) }}
                                </span>
                                <span class="ml-2 text-sm font-semibold text-zinc-900 dark:text-white">
                                    {{ $audit->auditable_type }} #{{ $audit->auditable_id }}
                                </span>
                            </div>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $audit->actor?->name ?? __('System') }} · {{ $audit->created_at?->timezone('Asia/Manila')->format('M j, Y · g:i:s A') }}
                            </p>
                        </div>
                        @if ($audit->after)
                            <pre class="mt-3 overflow-x-auto rounded-lg bg-zinc-50 p-3 text-xs text-zinc-600 dark:bg-zinc-800/60 dark:text-zinc-300">{{ json_encode($audit->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        @endif
                    </article>
                @empty
                    <p class="p-6 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No administrative audit entries found.') }}</p>
                @endforelse
            </div>
        </section>
    @else
        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->appointmentEvents as $event)
                    <article wire:key="appt-event-{{ $event->id }}" class="p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-bold uppercase text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                    {{ str_replace('_', ' ', $event->event_type) }}
                                </span>
                                <span class="ml-2 text-sm font-semibold text-zinc-900 dark:text-white">
                                    {{ $event->appointment?->reference_code }}
                                </span>
                                <span class="ml-1 text-xs text-zinc-500">
                                    ({{ $event->appointment?->patient?->user?->name }} · {{ $event->appointment?->doctor?->name }})
                                </span>
                            </div>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $event->actor?->name ?? __('System') }} · {{ $event->created_at?->timezone('Asia/Manila')->format('M j, Y · g:i:s A') }}
                            </p>
                        </div>
                        @if ($event->reason)
                            <p class="mt-2 text-xs text-zinc-600 dark:text-zinc-300">
                                <strong>{{ __('Reason:') }}</strong> {{ $event->reason }}
                            </p>
                        @endif
                    </article>
                @empty
                    <p class="p-6 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No appointment events found.') }}</p>
                @endforelse
            </div>
        </section>
    @endif
</div>

