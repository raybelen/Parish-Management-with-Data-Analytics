<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('service_types')->insertOrIgnore([
            'slug' => 'others', 'name' => 'Others', 'duration_minutes' => 60, 'buffer_minutes' => 0,
            'fee_cents' => 10000, 'requirements' => json_encode([], JSON_THROW_ON_ERROR),
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('service_types')->where('slug', 'others')->where('fee_cents', 0)->update(['fee_cents' => 10000, 'buffer_minutes' => 0]);
    }

    /** Retain the service because saved appointments may reference it. */
    public function down(): void {}
};
