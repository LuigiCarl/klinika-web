<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialties', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('specialty_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('appointment_duration_minutes')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE doctors ADD CONSTRAINT doctors_appointment_duration_check CHECK (appointment_duration_minutes IN (15, 30, 45, 60))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('doctors');
        Schema::dropIfExists('specialties');
    }
};
