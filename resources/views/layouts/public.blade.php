<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
        <header class="border-b border-slate-200 bg-white">
            <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8" aria-label="Main navigation">
                <a href="{{ route('home') }}" class="text-xl font-bold text-teal-700">klinika-web</a>
                <div class="flex items-center gap-4 text-sm font-medium">
                    <a href="{{ route('doctors.index') }}" class="hover:text-teal-700">Doctors</a>
                    @auth
                        <a href="{{ auth()->user()->hasRole(App\Role::Patient) ? route('patient.dashboard') : route('dashboard') }}" class="rounded-lg bg-teal-700 px-4 py-2 text-white hover:bg-teal-800">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="hover:text-teal-700">Log in</a>
                        <a href="{{ route('register') }}" class="rounded-lg bg-teal-700 px-4 py-2 text-white hover:bg-teal-800">Register</a>
                    @endauth
                </div>
            </nav>
        </header>
        <main>{{ $slot }}</main>
        @fluxScripts
    </body>
</html>
