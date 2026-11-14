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
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('operational');
            $table->string('name', 150);
            $table->string('location', 255)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('capacity');
            $table->boolean('active')->default(true);
            $table->json('opening_hours');
            $table->json('permitted_activities');
            $table->unsignedInteger('rental_fee_cents')->default(0);
            $table->string('rental_fee_basis')->default('per_reservation');
            $table->timestamps();
            $table->unique(['source', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};
