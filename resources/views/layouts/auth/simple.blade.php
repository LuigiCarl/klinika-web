<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-zinc-950 dark:text-white">
        <div class="grid min-h-svh lg:grid-cols-2">
            <aside class="relative hidden overflow-hidden bg-gradient-to-br from-teal-800 via-teal-700 to-cyan-600 p-12 text-white lg:flex lg:flex-col lg:justify-between">
                <a href="{{ route('home') }}" class="text-2xl font-bold">klinika-web</a>
                <div class="max-w-lg"><p class="text-sm font-bold uppercase tracking-widest text-teal-100">Clinic appointments made clear</p><h1 class="mt-4 text-4xl font-bold leading-tight">Find the right doctor and keep every appointment in one secure place.</h1><p class="mt-5 text-lg leading-8 text-teal-50">Request available schedules online and follow the clinic's confirmation status from your patient dashboard.</p></div>
                <p class="text-sm text-teal-100">Your account only shows your own appointment information.</p>
            </aside>
            <main class="flex min-h-svh items-center justify-center px-4 py-10 dark:bg-zinc-950 sm:px-8">
                <div class="w-full max-w-md">
                    <a href="{{ route('home') }}" class="mb-8 inline-flex items-center gap-2 text-xl font-bold text-teal-700 dark:text-teal-300 lg:hidden"><span class="flex size-9 items-center justify-center rounded-lg bg-teal-700 text-white">K</span>klinika-web</a>
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 sm:p-8">{{ $slot }}</div>
                    <p class="mt-5 text-center text-xs leading-5 text-slate-500 dark:text-zinc-400">Appointment information is sensitive. Always log out on shared devices.</p>
                </div>
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
