<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Which amenities a space has (DATABASE_ERD §3 `amenity_space`). `amenity_property` waits until a
     * source says which location has a property-wide amenity: SG-INV-1.3 does not.
     */
    public function up(): void
    {
        Schema::create('amenity_space', function (Blueprint $table) {
            $table->foreignId('amenity_id')->constrained()->restrictOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['amenity_id', 'space_id']);
            $table->index('space_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amenity_space');
    }
};
