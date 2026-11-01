<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->string('payment_option', 20)->default('walk_in');
            $table->string('payment_status', 20)->default('unpaid');
            $table->index(['payment_option', 'payment_status']);
        });

        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->foreignId('ledger_payment_id')->nullable()->unique()->constrained('payments')->restrictOnDelete();
            $table->string('provider', 30)->default('xendit');
            $table->string('environment', 20)->default('sandbox');
            $table->string('reference_id', 64)->unique();
            $table->string('provider_session_id')->nullable()->unique();
            $table->string('provider_payment_id')->nullable()->unique();
            $table->string('provider_business_id')->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3)->default('PHP');
            $table->string('status', 20)->default('creating');
            $table->string('payment_method', 60)->nullable();
            $table->text('checkout_url')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('last_event_at')->nullable();
            $table->timestamps();

            $table->index(['appointment_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex(['payment_option', 'payment_status']);
            $table->dropColumn(['payment_option', 'payment_status']);
        });
    }
};
