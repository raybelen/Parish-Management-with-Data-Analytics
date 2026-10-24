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
        Schema::create('monthly_statistics', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20)->default('operational');
            $table->string('service_type', 30);
            $table->date('month');
            $table->unsignedInteger('count')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('reference');
            $table->text('notes')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unique(['source', 'service_type', 'month']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_statistics');
    }
};
