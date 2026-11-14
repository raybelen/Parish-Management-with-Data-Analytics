<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('service_types')->insertOrIgnore([
            'slug' => 'confirmation',
            'name' => 'Confirmation',
            'duration_minutes' => 60,
            'buffer_minutes' => 30,
            'fee_cents' => 10000,
            'requirements' => json_encode([
                'baptismal_certificate' => ['label' => 'Baptismal Certificate', 'required' => true, 'multiple' => false],
            ], JSON_THROW_ON_ERROR),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('service_types')->where('slug', 'confirmation')->where('fee_cents', 0)->update(['fee_cents' => 10000]);
    }

    public function down(): void
    {
        // Retain the service because appointments and registers may already reference it.
    }
};
