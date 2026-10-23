<?php

use App\Concerns\ProfileValidationRules;
use App\Role;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';
    public string $phone = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->phone = Auth::user()->patient?->phone ?? '';
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();
        $validated = $this->validate([
            ...$this->profileRules($user->id),
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
        ]);

        $user->update(['name' => $validated['name'], 'email' => $validated['email']]);

        if ($user->hasRole(Role::Patient)) {
            $user->patient()->updateOrCreate([], ['phone' => $validated['phone'] ?: null]);
        }

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your contact information')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />
            <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

            @if (auth()->user()->hasRole(Role::Patient))
                <flux:input wire:model="phone" :label="__('Phone number')" type="tel" autocomplete="tel" />
            @endif

            <flux:button variant="primary" type="submit" data-test="update-profile-button">{{ __('Save') }}</flux:button>
        </form>

        @if (auth()->user()->patient === null)
            <livewire:pages::settings.delete-user-form />
        @else
            <div class="mt-10 rounded-xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800">
                <flux:heading>{{ __('Account closure') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Contact the clinic to request account closure. Appointment history must be handled according to the clinic retention policy.') }}</flux:text>
            </div>
        @endif
    </x-pages::settings.layout>
</section>
