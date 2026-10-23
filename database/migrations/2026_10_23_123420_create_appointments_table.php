<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id');
            $table->foreignId('practitioner_id');
            $table->foreignId('service_id');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->uuid('idempotency_key');
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['id', 'clinic_id']);
            $table->unique(['clinic_id', 'idempotency_key']);
            $table->foreign(['patient_id', 'clinic_id'])->references(['id', 'clinic_id'])->on('patients')->restrictOnDelete();
            $table->foreign(['practitioner_id', 'clinic_id'])->references(['id', 'clinic_id'])->on('practitioners')->restrictOnDelete();
            $table->foreign(['service_id', 'clinic_id'])->references(['id', 'clinic_id'])->on('services')->restrictOnDelete();
            $table->index(['clinic_id', 'starts_at', 'status']);
            $table->index(['clinic_id', 'practitioner_id', 'starts_at']);
            $table->index(['clinic_id', 'patient_id', 'starts_at']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_interval_check CHECK (ends_at > starts_at)');
            DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_status_check CHECK (status IN ('pending', 'confirmed', 'completed', 'cancelled', 'no_show'))");
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
            DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_practitioner_no_overlap EXCLUDE USING gist (practitioner_id WITH =, tsrange(starts_at, ends_at, '[)') WITH &&) WHERE (status IN ('pending', 'confirmed'))");
            DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_patient_no_overlap EXCLUDE USING gist (patient_id WITH =, tsrange(starts_at, ends_at, '[)') WITH &&) WHERE (status IN ('pending', 'confirmed'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
