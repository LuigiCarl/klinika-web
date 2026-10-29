<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

            <x-app-logo href="{{ route('dashboard') }}" wire:navigate />

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            <div x-data class="flex items-center me-2">
                <flux:button
                    variant="ghost"
                    size="sm"
                    icon="sun"
                    x-show="$flux.dark"
                    x-on:click="$flux.appearance = 'light'"
                    :tooltip="__('Switch to light mode')"
                />
                <flux:button
                    variant="ghost"
                    size="sm"
                    icon="moon"
                    x-show="! $flux.dark"
                    x-on:click="$flux.appearance = 'dark'"
                    :tooltip="__('Switch to dark mode')"
                />
            </div>

            <x-desktop-user-menu />
        </flux:header>

        <!-- Mobile Menu -->
        <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')">
                    <flux:sidebar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard')  }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Appearance')">
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
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />
        </flux:sidebar>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
