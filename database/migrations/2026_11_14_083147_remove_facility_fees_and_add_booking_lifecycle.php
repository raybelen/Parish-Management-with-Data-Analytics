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
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn(['rental_fee_cents', 'rental_fee_basis']);
        });
        Schema::table('facility_bookings', function (Blueprint $table) {
            $table->dropColumn(['rental_fee_cents', 'rental_fee_basis', 'total_fee_cents']);
            $table->dateTime('expires_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->unsignedInteger('rental_fee_cents')->default(0);
            $table->string('rental_fee_basis')->default('per_reservation');
        });
        Schema::table('facility_bookings', function (Blueprint $table) {
            $table->unsignedInteger('rental_fee_cents')->default(0);
            $table->string('rental_fee_basis')->default('per_reservation');
            $table->unsignedBigInteger('total_fee_cents')->default(0);
            $table->dropColumn('expires_at');
        });
    }
};
