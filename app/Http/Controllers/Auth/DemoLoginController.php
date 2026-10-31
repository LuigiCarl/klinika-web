<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\User;
use App\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class DemoLoginController extends Controller
{
    /**
     * Handle one-click demo login for portfolio reviewers.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(['admin', 'receptionist', 'patient'])],
        ]);

        $role = Role::from($validated['role']);
        $user = $this->resolveDemoUser($role);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        $roleLabel = match ($role) {
            Role::Admin => 'Clinic Administrator',
            Role::Receptionist => 'Receptionist Staff',
            Role::Patient => 'Patient',
        };

        return redirect()->intended(route('dashboard'))
            ->with('status', __("Logged in as demonstration {$roleLabel}."));
    }

    /**
     * Retrieve or provision the demonstration account for the requested role.
     */
    private function resolveDemoUser(Role $role): User
    {
        return match ($role) {
            Role::Admin => User::query()->firstOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'name' => 'Clinic Administrator',
                    'password' => Hash::make('password'),
                    'role' => Role::Admin,
                    'is_active' => true,
                ]
            ),
            Role::Receptionist => User::query()->firstOrCreate(
                ['email' => 'receptionist@example.com'],
                [
                    'name' => 'Elena Cruz',
                    'password' => Hash::make('password'),
                    'role' => Role::Receptionist,
                    'is_active' => true,
                ]
            ),
            Role::Patient => tap(
                User::query()->firstOrCreate(
                    ['email' => 'patient@example.com'],
                    [
                        'name' => 'Juan Dela Cruz',
                        'password' => Hash::make('password'),
                        'role' => Role::Patient,
                        'is_active' => true,
                    ]
                ),
                function (User $user): void {
                    Patient::query()->firstOrCreate(
                        ['user_id' => $user->id],
                        ['phone' => '09171234567']
                    );
                }
            ),
        };
    }
}
