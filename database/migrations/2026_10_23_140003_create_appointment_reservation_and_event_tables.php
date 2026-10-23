<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_slot_reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id');
            $table->unsignedBigInteger('doctor_id');
            $table->dateTimeTz('slot_starts_at');
            $table->timestamps();

            $table->unique(['doctor_id', 'slot_starts_at']);
            $table->unique(['appointment_id', 'slot_starts_at']);
            $table->foreign(['appointment_id', 'doctor_id'])
                ->references(['id', 'doctor_id'])
                ->on('appointments')
                ->cascadeOnDelete();
        });

        Schema::create('patient_slot_reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id');
            $table->unsignedBigInteger('patient_id');
            $table->dateTimeTz('slot_starts_at');
            $table->timestamps();

            $table->unique(['patient_id', 'slot_starts_at']);
            $table->unique(['appointment_id', 'slot_starts_at']);
            $table->foreign(['appointment_id', 'patient_id'])
                ->references(['id', 'patient_id'])
                ->on('appointments')
                ->cascadeOnDelete();
        });

        Schema::create('appointment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 50);
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20)->nullable();
            $table->string('reason', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['appointment_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_events');
        Schema::dropIfExists('patient_slot_reservations');
        Schema::dropIfExists('doctor_slot_reservations');
    }
};
