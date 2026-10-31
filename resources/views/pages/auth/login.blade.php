<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Welcome back')" :description="__('Log in to manage your clinic appointments')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <!-- Quick Demo Access (1-Click) -->
        <div class="rounded-xl border border-teal-200 bg-teal-50/60 p-4 dark:border-teal-900/60 dark:bg-teal-950/20">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-teal-800 dark:text-teal-300 flex items-center gap-1.5">
                    <flux:icon icon="bolt" variant="mini" class="size-4 text-teal-600 dark:text-teal-400" />
                    {{ __('1-Click Demo Access') }}
                </span>
                <span class="text-[11px] font-medium text-teal-700/80 dark:text-teal-400/80">{{ __('Instant Sign-in') }}</span>
            </div>

            <div class="grid grid-cols-3 gap-2">
                <form method="POST" action="{{ route('demo.login') }}">
                    @csrf
                    <input type="hidden" name="role" value="admin">
                    <button type="submit" class="w-full flex flex-col items-center justify-center py-2 px-1 rounded-lg border border-zinc-200 bg-white hover:bg-zinc-50 hover:border-teal-500 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800 dark:hover:border-teal-500 transition text-center shadow-xs group cursor-pointer">
                        <span class="font-semibold text-xs text-zinc-900 dark:text-zinc-100 group-hover:text-teal-600 dark:group-hover:text-teal-400">{{ __('Admin') }}</span>
                        <span class="text-[10px] text-zinc-500 dark:text-zinc-400 mt-0.5">{{ __('Clinic Mgr') }}</span>
                    </button>
                </form>

                <form method="POST" action="{{ route('demo.login') }}">
                    @csrf
                    <input type="hidden" name="role" value="receptionist">
                    <button type="submit" class="w-full flex flex-col items-center justify-center py-2 px-1 rounded-lg border border-zinc-200 bg-white hover:bg-zinc-50 hover:border-teal-500 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800 dark:hover:border-teal-500 transition text-center shadow-xs group cursor-pointer">
                        <span class="font-semibold text-xs text-zinc-900 dark:text-zinc-100 group-hover:text-teal-600 dark:group-hover:text-teal-400">{{ __('Receptionist') }}</span>
                        <span class="text-[10px] text-zinc-500 dark:text-zinc-400 mt-0.5">{{ __('Queue Triage') }}</span>
                    </button>
                </form>

                <form method="POST" action="{{ route('demo.login') }}">
                    @csrf
                    <input type="hidden" name="role" value="patient">
                    <button type="submit" class="w-full flex flex-col items-center justify-center py-2 px-1 rounded-lg border border-zinc-200 bg-white hover:bg-zinc-50 hover:border-teal-500 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800 dark:hover:border-teal-500 transition text-center shadow-xs group cursor-pointer">
                        <span class="font-semibold text-xs text-zinc-900 dark:text-zinc-100 group-hover:text-teal-600 dark:group-hover:text-teal-400">{{ __('Patient') }}</span>
                        <span class="text-[10px] text-zinc-500 dark:text-zinc-400 mt-0.5">{{ __('Book Visits') }}</span>
                    </button>
                </form>
            </div>
        </div>

        <div class="relative flex items-center justify-center">
            <div class="border-t border-zinc-200 dark:border-zinc-700 w-full"></div>
            <span class="bg-white dark:bg-zinc-900 px-3 text-xs text-zinc-400 uppercase tracking-wider shrink-0">
                {{ __('or continue with password') }}
            </span>
        </div>

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="you@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Keep me signed in on this device')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('New patient?') }}</span>
            <flux:link :href="route('register')" wire:navigate>{{ __('Create an account') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
