<?php

namespace Tests\Feature\Auth;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_routes_are_disabled(): void
    {
        $this->assertFalse(Route::has('verification.notice'));
        $this->assertFalse(Route::has('verification.send'));
        $this->assertFalse(Route::has('verification.verify'));
    }

    public function test_patient_without_email_verification_can_use_authenticated_pages(): void
    {
        $user = User::factory()->unverified()->create();
        Patient::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('patient.dashboard'))
            ->assertOk();
    }
}
