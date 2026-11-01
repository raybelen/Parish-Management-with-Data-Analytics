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
        Schema::create('priest_unavailabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->cascadeOnDelete();
            $table->date('unavailable_on');
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->unique(['personnel_id', 'unavailable_on']);
            $table->index('unavailable_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('priest_unavailabilities');
    }
};
