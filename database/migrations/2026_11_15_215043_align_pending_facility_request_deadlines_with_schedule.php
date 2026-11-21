<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('facility_bookings')
            ->where('status', 'pending')
            ->whereNull('cancelled_at')
            ->select(['id', 'starts_at'])
            ->orderBy('id')
            ->lazyById(100)
            ->each(function (object $booking): void {
                DB::table('facility_bookings')
                    ->where('id', $booking->id)
                    ->update(['expires_at' => Carbon::parse($booking->starts_at)->subHours(24)]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The previous deadline was relative to verification time and cannot be reconstructed.
    }
};
