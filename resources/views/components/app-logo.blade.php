@props([
    'sidebar' => false,
])

@php
    $brandName = in_array(config('app.name'), ['Laravel', 'klinika-web', ''], true) ? 'Klinika' : config('app.name', 'Klinika');
@endphp

@if($sidebar)
    <flux:sidebar.brand :name="$brandName" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-lg bg-teal-600 text-white shadow-sm dark:bg-teal-500">
            <x-app-logo-icon class="size-5 fill-current text-white" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="$brandName" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-lg bg-teal-600 text-white shadow-sm dark:bg-teal-500">
            <x-app-logo-icon class="size-5 fill-current text-white" />
        </x-slot>
    </flux:brand>
@endif
