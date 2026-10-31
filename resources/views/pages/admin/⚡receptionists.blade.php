<?php

use App\Models\AdministrativeAudit;
use App\Models\User;
use App\Role;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Receptionist Management')] class extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    /** @return Collection<int, User> */
    #[Computed]
    public function receptionists(): Collection
    {
        return User::query()
            ->where('role', Role::Receptionist)
            ->orderBy('name')
            ->get();
    }

    public function createReceptionist(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::default()],
        ]);

        DB::transaction(function () use ($validated): void {
            $receptionist = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => Role::Receptionist,
                'is_active' => true,
            ]);

            AdministrativeAudit::query()->create([
                'actor_id' => Auth::id(),
                'auditable_type' => 'User',
                'auditable_id' => $receptionist->id,
                'action' => 'created_receptionist',
                'before' => null,
                'after' => [
                    'name' => $receptionist->name,
                    'email' => $receptionist->email,
                    'role' => $receptionist->role->value,
                    'is_active' => $receptionist->is_active,
                ],
            ]);
        });

        $this->reset('name', 'email', 'password');
        unset($this->receptionists);
        Flux::toast(variant: 'success', text: __('Receptionist account created.'));
    }

    public function toggleReceptionist(int $userId): void
    {
        $receptionist = User::query()
            ->where('role', Role::Receptionist)
            ->findOrFail($userId);

        $before = [
            'is_active' => $receptionist->is_active,
        ];

        $receptionist->update([
            'is_active' => ! $receptionist->is_active,
        ]);

        AdministrativeAudit::query()->create([
            'actor_id' => Auth::id(),
            'auditable_type' => 'User',
            'auditable_id' => $receptionist->id,
            'action' => $receptionist->is_active ? 'activated_receptionist' : 'deactivated_receptionist',
            'before' => $before,
            'after' => [
                'is_active' => $receptionist->is_active,
            ],
        ]);

        unset($this->receptionists);
        Flux::toast(
            variant: 'success',
            text: $receptionist->is_active ? __('Receptionist activated.') : __('Receptionist deactivated.'),
        );
    }
};
?>

<div class="flex w-full flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Receptionist Management') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Provision and manage clinic front-desk receptionist staff accounts.') }}</flux:text>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Add Receptionist') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Create a new active staff account for queue triage and check-ins.') }}</flux:text>

            <form wire:submit="createReceptionist" class="mt-4 space-y-4">
                <flux:input wire:model="name" :label="__('Full name')" required />
                <flux:input wire:model="email" type="email" :label="__('Email address')" required />
                <flux:input wire:model="password" type="password" :label="__('Initial password')" required />
                <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
                    {{ __('Provision receptionist') }}
                </flux:button>
            </form>
        </section>

        <section class="xl:col-span-2 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Staff Directory') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Active and deactivated receptionist accounts.') }}</flux:text>
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->receptionists as $staff)
                    <div wire:key="receptionist-{{ $staff->id }}" class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="font-semibold text-zinc-900 dark:text-white">{{ $staff->name }}</p>
                                <flux:badge size="sm" :color="$staff->is_active ? 'emerald' : 'zinc'">
                                    {{ $staff->is_active ? __('Active') : __('Inactive') }}
                                </flux:badge>
                            </div>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $staff->email }}</p>
                        </div>
                        <div>
                            <flux:button
                                size="sm"
                                wire:click="toggleReceptionist({{ $staff->id }})"
                                wire:loading.attr="disabled"
                            >
                                {{ $staff->is_active ? __('Deactivate') : __('Activate') }}
                            </flux:button>
                        </div>
                    </div>
                @empty
                    <p class="p-6 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No receptionists provisioned yet.') }}</p>
                @endforelse
            </div>
        </section>
    </div>
</div>

