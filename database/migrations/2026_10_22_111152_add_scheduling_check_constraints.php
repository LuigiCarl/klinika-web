<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE services ADD CONSTRAINT services_duration_minutes_check CHECK (duration_minutes BETWEEN 5 AND 480)');
        DB::statement('ALTER TABLE availability_rules ADD CONSTRAINT availability_weekday_check CHECK (weekday BETWEEN 1 AND 7)');
        DB::statement('ALTER TABLE availability_rules ADD CONSTRAINT availability_interval_check CHECK (ends_at > starts_at)');
        DB::statement('ALTER TABLE availability_rules ADD CONSTRAINT availability_effective_dates_check CHECK (effective_until IS NULL OR effective_from IS NULL OR effective_until >= effective_from)');
        DB::statement('ALTER TABLE schedule_exceptions ADD CONSTRAINT schedule_exception_interval_check CHECK (ends_at > starts_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE schedule_exceptions DROP CONSTRAINT schedule_exception_interval_check');
        DB::statement('ALTER TABLE availability_rules DROP CONSTRAINT availability_effective_dates_check');
        DB::statement('ALTER TABLE availability_rules DROP CONSTRAINT availability_interval_check');
        DB::statement('ALTER TABLE availability_rules DROP CONSTRAINT availability_weekday_check');
        DB::statement('ALTER TABLE services DROP CONSTRAINT services_duration_minutes_check');
    }
};
