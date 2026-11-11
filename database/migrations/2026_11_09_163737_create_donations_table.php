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
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->uuid('idempotency_key')->unique();
            $table->string('submission_hash', 64);
            $table->string('source', 20)->default('operational');
            $table->string('donor_name');
            $table->string('donor_email')->nullable();
            $table->string('donor_phone', 40)->nullable();
            $table->text('donor_address')->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->date('donated_on');
            $table->string('category', 40);
            $table->string('method', 40);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('recorded');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['source', 'donated_on', 'id']);
        });

        Schema::create('donation_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donation_id')->constrained()->restrictOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->string('kind', 20);
            $table->unsignedInteger('donation_version');
            $table->json('before');
            $table->json('payload');
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['donation_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donation_adjustments');
        Schema::dropIfExists('donations');
    }
};
