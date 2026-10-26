<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            $table->unsignedInteger('group_capacity')->nullable();
            $table->decimal('group_fee', 10, 2)->nullable();
        });
        Schema::table('appointments', function (Blueprint $table): void {
            $table->foreignId('group_baptism_session_id')->nullable()->constrained('schedules')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('group_baptism_session_id');
        });
        Schema::table('schedules', function (Blueprint $table): void {
            $table->dropColumn(['group_capacity', 'group_fee']);
        });
    }
};
