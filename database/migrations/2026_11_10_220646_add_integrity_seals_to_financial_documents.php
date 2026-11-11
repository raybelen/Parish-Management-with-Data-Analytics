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
        Schema::table('appointment_invoices', function (Blueprint $table) {
            $table->string('integrity_seal', 64)->nullable()->unique();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->json('receipt_snapshot')->nullable();
            $table->string('integrity_seal', 64)->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['integrity_seal']);
            $table->dropColumn(['receipt_snapshot', 'integrity_seal']);
        });
        Schema::table('appointment_invoices', function (Blueprint $table) {
            $table->dropUnique(['integrity_seal']);
            $table->dropColumn('integrity_seal');
        });
    }
};
