<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('timezone', 64)->default('Asia/Manila');
            $table->unsignedSmallInteger('booking_window_days')->default(30);
            $table->unsignedSmallInteger('minimum_lead_hours')->default(4);
            $table->unsignedSmallInteger('minimum_review_hours')->default(2);
            $table->unsignedSmallInteger('approval_expiry_hours')->default(24);
            $table->unsignedSmallInteger('cancellation_cutoff_hours')->default(2);
            $table->unsignedSmallInteger('no_show_grace_minutes')->default(15);
            $table->timestamps();
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE clinic_settings ADD CONSTRAINT clinic_settings_single_row_check CHECK (id = 1)');
            DB::statement('ALTER TABLE clinic_settings ADD CONSTRAINT clinic_settings_policy_values_check CHECK (booking_window_days > 0 AND minimum_lead_hours > 0 AND minimum_review_hours > 0 AND approval_expiry_hours > 0 AND cancellation_cutoff_hours > 0 AND no_show_grace_minutes > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_settings');
    }
};
