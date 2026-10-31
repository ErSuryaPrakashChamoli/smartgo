<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Individually tracked seats in a per-seat or hybrid zone (DATABASE_ERD §3, §3.2). A seat is
     * deactivated, never deleted, so the allocations that used it keep their reference.
     */
    public function up(): void
    {
        Schema::create('space_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained()->restrictOnDelete();
            $table->string('label', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['space_id', 'label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('space_seats');
    }
};
