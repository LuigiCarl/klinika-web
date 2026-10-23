<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\User;
use App\Role;
use Illuminate\Database\Seeder;

class ClinicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $administrator = User::factory()->administrator()->create([
            'name' => 'Clinic Administrator',
            'email' => 'admin@example.com',
            'role' => Role::Administrator,
        ]);

        $clinic = Clinic::factory()->create([
            'name' => 'Klinika Demo Clinic',
            'slug' => 'klinika-demo-clinic',
        ]);

        $clinic->users()->attach($administrator, ['role' => Role::Administrator->value]);
    }
}
