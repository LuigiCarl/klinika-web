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
        Schema::create('practitioner_service', function (Blueprint $table) {
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('practitioner_id');
            $table->foreignId('service_id');
            $table->timestamps();

            $table->primary(['practitioner_id', 'service_id']);
            $table->foreign(['practitioner_id', 'clinic_id'])
                ->references(['id', 'clinic_id'])
                ->on('practitioners')
                ->cascadeOnDelete();
            $table->foreign(['service_id', 'clinic_id'])
                ->references(['id', 'clinic_id'])
                ->on('services')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('practitioner_service');
    }
};
