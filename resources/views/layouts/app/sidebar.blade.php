<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ auth()->user()->hasRole(App\Role::Patient) ? route('patient.dashboard') : route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    @if (auth()->user()->hasRole(App\Role::Patient))
                        <flux:sidebar.item icon="users" :href="route('doctors.index')" :current="request()->routeIs('doctors.*') || request()->routeIs('patient.book')" wire:navigate>
                            {{ __('Browse doctors') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="calendar-days" :href="route('patient.appointments.index')" :current="request()->routeIs('patient.appointments.*')" wire:navigate>
                            {{ __('My appointments') }}
                        </flux:sidebar.item>
                    @endif
                    @if (auth()->user()->hasRole(App\Role::Admin))
                        <flux:sidebar.item icon="users" :href="route('admin.doctors')" :current="request()->routeIs('admin.doctors')" wire:navigate>
                            {{ __('Doctors & schedules') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="chart-bar" :href="route('admin.analytics')" :current="request()->routeIs('admin.analytics')" wire:navigate>
                            {{ __('Analytics & KPIs') }}
                        </flux:sidebar.item>
                    @endif
                    @if (auth()->user()->hasRole(App\Role::Receptionist))
                        @php
                            $pendingRequestsCount = App\Models\Appointment::query()
                                ->where('status', App\AppointmentStatus::Pending)
                                ->count();
                        @endphp
                        <flux:sidebar.item
                            icon="inbox"
                            :href="route('receptionist.pending')"
                            :current="request()->routeIs('receptionist.pending')"
                            :badge="$pendingRequestsCount > 0 ? (string) $pendingRequestsCount : null"
                            badge:color="amber"
                            wire:navigate
                        >
                            {{ __('Pending requests') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="calendar-days" :href="route('receptionist.calendar')" :current="request()->routeIs('receptionist.calendar')" wire:navigate>{{ __('Calendar') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="magnifying-glass" :href="route('receptionist.appointments.index')" :current="request()->routeIs('receptionist.appointments.*') || request()->routeIs('receptionist.history')" wire:navigate>{{ __('Appointments & history') }}</flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <div x-data class="w-full">
                    <flux:sidebar.item
                        icon="sun"
                        x-show="$flux.dark"
                        x-on:click="$flux.appearance = 'light'"
                        class="cursor-pointer"
                    >
                        {{ __('Light mode') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item
                        icon="moon"
                        x-show="! $flux.dark"
                        x-on:click="$flux.appearance = 'dark'"
                        class="cursor-pointer"
                    >
                        {{ __('Dark mode') }}
                    </flux:sidebar.item>
                </div>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.submenu icon="user-group" :heading="__('Switch Role (Demo)')">
                        <form method="POST" action="{{ route('demo.login') }}">
                            @csrf
                            <input type="hidden" name="role" value="admin">
                            <flux:menu.item as="button" type="submit" icon="shield-check" class="w-full cursor-pointer">
                                {{ __('Clinic Admin') }}
                            </flux:menu.item>
                        </form>
                        <form method="POST" action="{{ route('demo.login') }}">
                            @csrf
                            <input type="hidden" name="role" value="receptionist">
                            <flux:menu.item as="button" type="submit" icon="inbox-stack" class="w-full cursor-pointer">
                                {{ __('Receptionist') }}
                            </flux:menu.item>
                        </form>
                        <form method="POST" action="{{ route('demo.login') }}">
                            @csrf
                            <input type="hidden" name="role" value="patient">
                            <flux:menu.item as="button" type="submit" icon="user" class="w-full cursor-pointer">
                                {{ __('Patient') }}
                            </flux:menu.item>
                        </form>
                    </flux:menu.submenu>

                    <flux:menu.separator />

                    <div x-data>
                        <flux:menu.item icon="sun" x-show="$flux.dark" x-on:click="$flux.appearance = 'light'">
                            {{ __('Light mode') }}
                        </flux:menu.item>
                        <flux:menu.item icon="moon" x-show="! $flux.dark" x-on:click="$flux.appearance = 'dark'">
                            {{ __('Dark mode') }}
                        </flux:menu.item>
                    </div>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
