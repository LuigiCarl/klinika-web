<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->string('reference_code', 32)->unique();
            $table->dateTimeTz('starts_at');
            $table->dateTimeTz('ends_at');
            $table->unsignedTinyInteger('duration_minutes');
            $table->string('status', 20)->default('pending');
            $table->dateTimeTz('expires_at');
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTimeTz('approved_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTimeTz('cancelled_at')->nullable();
            $table->uuid('idempotency_key');
            $table->string('request_fingerprint', 64);
            $table->timestamps();

            $table->unique(['patient_id', 'idempotency_key']);
            $table->unique(['id', 'doctor_id']);
            $table->unique(['id', 'patient_id']);
            $table->index(['doctor_id', 'starts_at']);
            $table->index(['patient_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
            $table->index(['status', 'expires_at']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_interval_check CHECK (ends_at > starts_at)');
            DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_duration_check CHECK (duration_minutes IN (15, 30, 45, 60))');
            DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_status_check CHECK (status IN ('pending', 'confirmed', 'rejected', 'expired', 'cancelled', 'checked_in', 'completed', 'no_show'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
