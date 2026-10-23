<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use App\Role;

class AppointmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasRole(Role::Patient, Role::Receptionist);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Appointment $appointment): bool
    {
        return $user->is_active && $user->hasRole(Role::Receptionist)
            || $this->owns($user, $appointment);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Appointment $appointment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Appointment $appointment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Appointment $appointment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Appointment $appointment): bool
    {
        return false;
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $this->owns($user, $appointment);
    }

    public function transition(User $user, Appointment $appointment): bool
    {
        return $user->is_active && $user->hasRole(Role::Receptionist);
    }

    private function owns(User $user, Appointment $appointment): bool
    {
        return $user->is_active
            && $user->hasRole(Role::Patient)
            && $appointment->patient()->where('user_id', $user->id)->exists();
    }
}
