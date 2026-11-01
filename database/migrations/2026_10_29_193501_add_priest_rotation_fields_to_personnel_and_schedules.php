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
        Schema::table('personnel', function (Blueprint $table): void {
            $table->unsignedSmallInteger('rotation_position')->nullable()->unique();
        });
        Schema::table('schedules', function (Blueprint $table): void {
            $table->boolean('priest_required')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            $table->dropColumn('priest_required');
        });
        Schema::table('personnel', function (Blueprint $table): void {
            $table->dropUnique(['rotation_position']);
            $table->dropColumn('rotation_position');
        });
    }
};
