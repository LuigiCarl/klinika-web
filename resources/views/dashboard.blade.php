<x-layouts::app :title="__('Dashboard')">
    @if (auth()->user()->hasRole(App\Role::Patient))
        <livewire:pages::patient.dashboard />
    @elseif (auth()->user()->hasRole(App\Role::Receptionist))
        <livewire:pages::receptionist.dashboard />
    @else
        <livewire:pages::admin.dashboard />
    @endif
</x-layouts::app>
