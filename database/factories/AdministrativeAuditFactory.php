<?php

namespace Database\Factories;

use App\Models\AdministrativeAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdministrativeAudit> */
class AdministrativeAuditFactory extends Factory
{
    public function definition(): array
    {
        return [
            'actor_id' => User::factory()->admin(),
            'auditable_type' => 'doctor',
            'auditable_id' => fake()->numberBetween(1, 1000),
            'action' => 'updated',
        ];
    }
}
