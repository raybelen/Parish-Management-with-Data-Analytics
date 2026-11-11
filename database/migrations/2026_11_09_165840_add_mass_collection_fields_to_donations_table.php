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
        Schema::table('donations', function (Blueprint $table) {
            $table->string('kind', 30)->default('individual');
            $table->boolean('is_anonymous')->default(false);
            $table->foreignId('mass_collection_id')->nullable()->constrained('donations')->restrictOnDelete();
            $table->string('church')->nullable();
            $table->string('mass_time', 5)->nullable();
            $table->string('collection_type', 20)->nullable();
            $table->string('collection_purpose')->nullable();
            $table->string('counted_by')->nullable();
            $table->string('verified_by')->nullable();
            $table->string('collection_key', 64)->nullable()->unique();
            $table->index(['source', 'kind', 'donated_on']);
            $table->index(['mass_collection_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropForeign(['mass_collection_id']);
            $table->dropUnique(['collection_key']);
            $table->dropIndex(['source', 'kind', 'donated_on']);
            $table->dropIndex(['mass_collection_id', 'status']);
            $table->dropColumn(['kind', 'is_anonymous', 'mass_collection_id', 'church', 'mass_time', 'collection_type', 'collection_purpose', 'counted_by', 'verified_by', 'collection_key']);
        });
    }
};
