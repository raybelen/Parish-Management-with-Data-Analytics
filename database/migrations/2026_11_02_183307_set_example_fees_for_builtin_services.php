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
        DB::table('service_types')
            ->whereIn('slug', ['baptism', 'wedding', 'funeral'])
            ->where('fee_cents', 0)
            ->update(['fee_cents' => 10000]);
    }

    /**
     * The data migration is intentionally irreversible because staff may edit fees after deployment.
     */
    public function down(): void {}
};
