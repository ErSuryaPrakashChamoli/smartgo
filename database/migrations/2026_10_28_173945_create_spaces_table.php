<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The canonical record of each physical workspace (DATABASE_ERD §3, BR-SOT-1). Codes are permanent
     * (BR-INV-2): a removed space is soft-deleted, so its code is never reused. The booking limits of rooms
     * booked by time come from the inventory import (SG-INV-1.3, V-115); bookings use them in Phase 8.
     */
    public function up(): void
    {
        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('floor_id')->constrained()->restrictOnDelete();
            $table->foreignId('space_type_id')->constrained()->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name', 120)->nullable();
            $table->string('inventory_mode', 20);
            $table->unsignedSmallInteger('capacity_seats');
            $table->unsignedInteger('area_sqft')->nullable();
            $table->string('operational_status', 20)->default('active');
            $table->boolean('is_publicly_listable')->default(false);
            $table->unsignedSmallInteger('turnaround_minutes')->nullable();
            $table->unsignedSmallInteger('min_booking_minutes')->nullable();
            $table->unsignedSmallInteger('max_booking_minutes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['property_id', 'space_type_id', 'operational_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spaces');
    }
};
