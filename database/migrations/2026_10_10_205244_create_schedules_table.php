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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20)->default('operational');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('location')->nullable();
            $table->string('department')->nullable();
            $table->string('category', 80);
            $table->foreignId('personnel_id')->nullable()->constrained('personnel')->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 20)->default('scheduled');
            $table->uuid('series_id')->nullable()->index();
            $table->string('recurrence', 20)->default('none');
            $table->date('recurrence_until')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['source', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
