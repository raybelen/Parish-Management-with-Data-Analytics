<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['baptism' => 150000, 'wedding' => 690000, 'confirmation' => 50000, 'funeral' => 1670000] as $slug => $feeCents) {
            DB::table('service_types')->where('slug', $slug)->update(['fee_cents' => $feeCents]);
        }
    }

    /** Existing prices cannot be reconstructed; restore them with an explicit forward update. */
    public function down(): void {}
};
