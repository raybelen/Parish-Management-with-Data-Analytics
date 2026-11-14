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
        DB::table('service_types')->where('slug', 'confirmation')->update(['buffer_minutes' => 0]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('service_types')->where('slug', 'confirmation')->update(['buffer_minutes' => 30]);
    }
};
