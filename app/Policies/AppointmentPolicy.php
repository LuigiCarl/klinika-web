<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use App\Role;
use Illuminate\Auth\Access\Response;

class AppointmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->clinics()->exists();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Appointment $appointment): Response|bool
    {
        $isStaff = $user->hasClinicRole($appointment->clinic, Role::Administrator, Role::Receptionist);
        $isAssignedPractitioner = $appointment->practitioner->user_id === $user->id
            && $user->hasClinicRole($appointment->clinic, Role::Practitioner);

        return $isStaff || $isAssignedPractitioner ? true : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->clinics()->wherePivotIn('role', [Role::Administrator->value, Role::Receptionist->value])->exists();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Appointment $appointment): Response|bool
    {
        return $user->hasClinicRole($appointment->clinic, Role::Administrator, Role::Receptionist)
            ? true
            : Response::denyAsNotFound();
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
}
