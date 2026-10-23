<?php

namespace App\Policies;

use App\Models\Clinic;
use App\Models\User;
use App\Role;
use Illuminate\Auth\Access\Response;

class ClinicPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Administrator)
            || $user->clinics()->wherePivotIn('role', [Role::Administrator->value, Role::Receptionist->value, Role::Practitioner->value])->exists();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Clinic $clinic): Response|bool
    {
        return $user->clinics()->whereKey($clinic->getKey())->exists()
            ? true
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(Role::Administrator);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Clinic $clinic): Response|bool
    {
        return $this->isClinicAdministrator($user, $clinic);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Clinic $clinic): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Clinic $clinic): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Clinic $clinic): bool
    {
        return false;
    }

    public function configure(User $user, Clinic $clinic): Response|bool
    {
        return $this->isClinicAdministrator($user, $clinic);
    }

    public function manageAppointments(User $user, Clinic $clinic): Response|bool
    {
        return $user->hasClinicRole($clinic, Role::Administrator, Role::Receptionist)
            ? true
            : Response::denyAsNotFound();
    }

    public function viewAppointments(User $user, Clinic $clinic): Response|bool
    {
        return $user->hasClinicRole($clinic, Role::Administrator, Role::Receptionist, Role::Practitioner)
            ? true
            : Response::denyAsNotFound();
    }

    public function viewAppointmentAudits(User $user, Clinic $clinic): Response|bool
    {
        return $user->hasClinicRole($clinic, Role::Administrator)
            ? true
            : Response::denyAsNotFound();
    }

    private function isClinicAdministrator(User $user, Clinic $clinic): Response|bool
    {
        return $user->hasClinicRole($clinic, Role::Administrator)
            ? true
            : Response::denyAsNotFound();
    }
}
