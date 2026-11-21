<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('facility_bookings')
            ->where('status', 'unverified')
            ->whereNull('cancelled_at')
            ->update([
                'status' => 'expired',
                'cancelled_at' => now(),
                'cancellation_reason' => 'Email verification was retired. Submit a new request if the facility is still needed.',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The original verification deadline cannot be reconstructed.
    }
};
