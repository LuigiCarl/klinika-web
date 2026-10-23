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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth')->nullable();
            $table->string('mobile', 40)->nullable();
            $table->string('normalized_mobile', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('normalized_email')->nullable();
            $table->timestamps();

            $table->unique(['id', 'clinic_id']);
            $table->index(['clinic_id', 'last_name', 'first_name']);
            $table->index(['clinic_id', 'normalized_mobile']);
            $table->index(['clinic_id', 'normalized_email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
