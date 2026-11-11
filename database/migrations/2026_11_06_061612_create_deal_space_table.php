<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The spaces proposed on a deal (DATABASE_ERD §6 `deal_space`, FR-CRM-4), from the canonical `spaces`: a
     * whole space, one seat of a per-seat or hybrid zone, or a number of seats of a pool, as an allocation
     * would take them. When the deal is won, each line's allocation is linked here (BR-CRM-7), so
     * `space_allocations` itself is unchanged (the ERD's `space_allocations.deal_id`).
     */
    public function up(): void
    {
        Schema::create('deal_space', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->restrictOnDelete();
            $table->foreignId('space_id')->constrained()->restrictOnDelete();
            $table->foreignId('space_seat_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('seats');
            $table->foreignId('space_allocation_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['deal_id', 'space_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deal_space');
    }
};
