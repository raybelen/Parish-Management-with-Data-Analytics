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
        Schema::table('office_notifications', function (Blueprint $table) {
            $table->foreignId('facility_booking_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('office_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('facility_booking_id');
        });
    }
};
