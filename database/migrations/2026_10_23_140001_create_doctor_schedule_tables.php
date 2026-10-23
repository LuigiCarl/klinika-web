<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->index(['doctor_id', 'weekday', 'effective_from', 'effective_until'], 'doctor_schedule_lookup_index');
        });

        Schema::create('doctor_unavailabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->dateTimeTz('starts_at');
            $table->dateTimeTz('ends_at');
            $table->string('reason', 255);
            $table->timestamps();

            $table->index(['doctor_id', 'starts_at', 'ends_at'], 'doctor_unavailability_lookup_index');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE doctor_schedules ADD CONSTRAINT doctor_schedules_weekday_check CHECK (weekday BETWEEN 1 AND 7)');
            DB::statement('ALTER TABLE doctor_schedules ADD CONSTRAINT doctor_schedules_interval_check CHECK (end_time > start_time)');
            DB::statement('ALTER TABLE doctor_schedules ADD CONSTRAINT doctor_schedules_effective_dates_check CHECK (effective_until IS NULL OR effective_from IS NULL OR effective_until >= effective_from)');
            DB::statement('ALTER TABLE doctor_unavailabilities ADD CONSTRAINT doctor_unavailabilities_interval_check CHECK (ends_at > starts_at)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_unavailabilities');
        Schema::dropIfExists('doctor_schedules');
    }
};
