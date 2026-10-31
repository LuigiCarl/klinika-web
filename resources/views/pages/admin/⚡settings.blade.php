<?php

use App\Models\AdministrativeAudit;
use App\Models\ClinicSetting;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Clinic Settings')] class extends Component
{
    public string $timezone = 'Asia/Manila';

    public string $bookingWindowDays = '30';

    public string $minimumLeadHours = '4';

    public string $minimumReviewHours = '2';

    public string $approvalExpiryHours = '24';

    public string $cancellationCutoffHours = '2';

    public string $noShowGraceMinutes = '15';

    public function mount(): void
    {
        $settings = ClinicSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'timezone' => 'Asia/Manila',
                'booking_window_days' => 30,
                'minimum_lead_hours' => 4,
                'minimum_review_hours' => 2,
                'approval_expiry_hours' => 24,
                'cancellation_cutoff_hours' => 2,
                'no_show_grace_minutes' => 15,
            ]
        );

        $this->timezone = $settings->timezone;
        $this->bookingWindowDays = (string) $settings->booking_window_days;
        $this->minimumLeadHours = (string) $settings->minimum_lead_hours;
        $this->minimumReviewHours = (string) $settings->minimum_review_hours;
        $this->approvalExpiryHours = (string) $settings->approval_expiry_hours;
        $this->cancellationCutoffHours = (string) $settings->cancellation_cutoff_hours;
        $this->noShowGraceMinutes = (string) $settings->no_show_grace_minutes;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'timezone' => ['required', 'string', 'timezone'],
            'bookingWindowDays' => ['required', 'integer', 'between:1,180'],
            'minimumLeadHours' => ['required', 'integer', 'between:1,72'],
            'minimumReviewHours' => ['required', 'integer', 'between:1,72'],
            'approvalExpiryHours' => ['required', 'integer', 'between:1,168'],
            'cancellationCutoffHours' => ['required', 'integer', 'between:0,72'],
            'noShowGraceMinutes' => ['required', 'integer', 'between:0,120'],
        ]);

        DB::transaction(function () use ($validated): void {
            $settings = ClinicSetting::query()->lockForUpdate()->findOrFail(1);
            $before = $settings->getAttributes();

            $settings->update([
                'timezone' => $validated['timezone'],
                'booking_window_days' => (int) $validated['bookingWindowDays'],
                'minimum_lead_hours' => (int) $validated['minimumLeadHours'],
                'minimum_review_hours' => (int) $validated['minimumReviewHours'],
                'approval_expiry_hours' => (int) $validated['approvalExpiryHours'],
                'cancellation_cutoff_hours' => (int) $validated['cancellationCutoffHours'],
                'no_show_grace_minutes' => (int) $validated['noShowGraceMinutes'],
            ]);

            AdministrativeAudit::query()->create([
                'actor_id' => Auth::id(),
                'auditable_type' => 'ClinicSetting',
                'auditable_id' => $settings->id,
                'action' => 'updated_clinic_settings',
                'before' => $before,
                'after' => $settings->getAttributes(),
            ]);
        });

        Flux::toast(variant: 'success', text: __('Clinic operational rules updated.'));
    }
};
?>

<div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Clinic Operational Settings') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Configure clinic scheduling windows, approval expiry SLA, cancellation cutoffs, and check-in grace periods.') }}</flux:text>
    </div>

    <section class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <form wire:submit="save" class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <flux:input wire:model="timezone" :label="__('Clinic Timezone')" required />
            </div>

            <flux:input wire:model="bookingWindowDays" type="number" min="1" max="180" :label="__('Booking Window (Days)')" required />
            <flux:input wire:model="minimumLeadHours" type="number" min="1" max="72" :label="__('Minimum Booking Lead Time (Hours)')" required />
            <flux:input wire:model="minimumReviewHours" type="number" min="1" max="72" :label="__('Minimum Review Lead Time (Hours)')" required />
            <flux:input wire:model="approvalExpiryHours" type="number" min="1" max="168" :label="__('Pending Approval Expiry (Hours)')" required />
            <flux:input wire:model="cancellationCutoffHours" type="number" min="0" max="72" :label="__('Self-Service Cancellation Cutoff (Hours)')" required />
            <flux:input wire:model="noShowGraceMinutes" type="number" min="0" max="120" :label="__('No-Show Grace Period (Minutes)')" required />

            <div class="flex justify-end sm:col-span-2">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    {{ __('Save operational rules') }}
                </flux:button>
            </div>
        </form>
    </section>
</div>

