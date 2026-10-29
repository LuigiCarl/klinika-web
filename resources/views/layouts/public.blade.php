<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-zinc-900 dark:text-zinc-100">
        <header class="border-b border-slate-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8" aria-label="Main navigation">
                <a href="{{ route('home') }}" class="flex items-center gap-2 text-xl font-bold text-teal-700 dark:text-teal-400">
                    <span class="flex aspect-square size-7 items-center justify-center rounded-lg bg-teal-600 text-white shadow-sm dark:bg-teal-500">
                        <x-app-logo-icon class="size-4 fill-current text-white" />
                    </span>
                    Klinika
                </a>
                <div class="flex items-center gap-4 text-sm font-medium">
                    <a href="{{ route('doctors.index') }}" class="hover:text-teal-700 dark:hover:text-teal-400">Doctors</a>

                    <div x-data class="flex items-center">
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

                    @auth
                        <a href="{{ auth()->user()->hasRole(App\Role::Patient) ? route('patient.dashboard') : route('dashboard') }}" class="rounded-lg bg-teal-700 px-4 py-2 text-white hover:bg-teal-800 dark:bg-teal-600 dark:hover:bg-teal-700">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="hover:text-teal-700 dark:hover:text-teal-400">Log in</a>
                        <a href="{{ route('register') }}" class="rounded-lg bg-teal-700 px-4 py-2 text-white hover:bg-teal-800 dark:bg-teal-600 dark:hover:bg-teal-700">Register</a>
                    @endauth
                </div>
            </nav>
        </header>
        <main>{{ $slot }}</main>
        @fluxScripts
    </body>
</html>
