<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <header class="border-b border-slate-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
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
        <main>
            <section class="mx-auto grid max-w-7xl items-center gap-10 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-28">
                <div>
                    <p class="font-semibold text-teal-700 dark:text-teal-400">Simple, secure clinic appointments</p>
                    <h1 class="mt-3 text-4xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-6xl">Your care starts with the right schedule.</h1>
                    <p class="mt-6 max-w-xl text-lg leading-8 text-slate-600 dark:text-zinc-400">Browse available doctors and request an appointment online. The clinic reviews every request before confirmation.</p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('doctors.index') }}" class="rounded-lg bg-teal-700 px-5 py-3 text-center font-semibold text-white hover:bg-teal-800 dark:bg-teal-600 dark:hover:bg-teal-700">Browse doctors</a>
                        @guest
                            <a href="{{ route('register') }}" class="rounded-lg border border-slate-300 bg-white px-5 py-3 text-center font-semibold hover:border-teal-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:hover:border-teal-500">Create patient account</a>
                        @endguest
                    </div>
                </div>
                <div class="rounded-3xl bg-gradient-to-br from-teal-700 to-cyan-600 p-8 text-white shadow-xl sm:p-12">
                    <p class="text-sm font-bold uppercase tracking-widest text-teal-100">How it works</p>
                    <ol class="mt-6 space-y-6">
                        <li>
                            <span class="font-bold">1. Choose a doctor</span>
                            <p class="mt-1 text-teal-50">Review specialties and clinic schedules.</p>
                        </li>
                        <li>
                            <span class="font-bold">2. Pick an open time</span>
                            <p class="mt-1 text-teal-50">Availability is checked again when you submit.</p>
                        </li>
                        <li>
                            <span class="font-bold">3. Track your request</span>
                            <p class="mt-1 text-teal-50">See pending and confirmed appointments in one place.</p>
                        </li>
                    </ol>
                </div>
            </section>
        </main>
        @fluxScripts
    </body>
</html>
