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
        Schema::create('appointment_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number')->unique();
            $table->unsignedInteger('revision');
            $table->string('parish_name');
            $table->string('client_name');
            $table->string('appointment_reference');
            $table->string('service_label');
            $table->string('source', 20);
            $table->unsignedBigInteger('amount_cents');
            $table->timestamp('issued_at');
            $table->timestamps();
            $table->unique(['appointment_id', 'revision']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_invoices');
    }
};
