<flux:dropdown position="bottom" align="start">
    <flux:sidebar.profile
        :name="auth()->user()->name"
        :initials="auth()->user()->initials()"
        icon:trailing="chevrons-up-down"
        data-test="sidebar-menu-button"
    />

    <flux:menu>
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
        <flux:menu.submenu icon="swatch" :heading="__('Theme')">
            <flux:menu.item icon="sun" x-data x-on:click="$flux.appearance = 'light'">
                {{ __('Light') }}
            </flux:menu.item>
            <flux:menu.item icon="moon" x-data x-on:click="$flux.appearance = 'dark'">
                {{ __('Dark') }}
            </flux:menu.item>
            <flux:menu.item icon="computer-desktop" x-data x-on:click="$flux.appearance = 'system'">
                {{ __('System') }}
            </flux:menu.item>
        </flux:menu.submenu>
        <flux:menu.separator />
        <flux:menu.radio.group>
            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                {{ __('Settings') }}
            </flux:menu.item>
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
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
