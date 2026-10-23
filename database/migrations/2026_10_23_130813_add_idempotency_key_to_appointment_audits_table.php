<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('appointment_audits', function (Blueprint $table) {
            $table->uuid('idempotency_key')->nullable()->after('action');
            $table->unique(['clinic_id', 'idempotency_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointment_audits', function (Blueprint $table) {
            $table->dropUnique(['clinic_id', 'idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
