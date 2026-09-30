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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->string('status')->default('pending');
            $table->string('client_full_name');
            $table->string('client_contact_number', 30);
            $table->string('client_contact_key', 30);
            $table->string('client_email')->nullable();
            $table->text('client_address');
            $table->string('preferred_contact_method', 20);
            $table->string('service_type', 30);
            $table->date('preferred_date');
            $table->time('preferred_time');
            $table->string('preferred_church');
            $table->text('additional_notes')->nullable();
            $table->json('service_details');
            $table->json('documents')->nullable();
            $table->timestamps();

            $table->index(['status', 'preferred_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
