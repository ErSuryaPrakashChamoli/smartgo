<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The named nodes found in a 3D model at upload (ADR-0009): spaces, amenities and camera positions.
     * `reference` is the part after the prefix: a space code, an amenity name or a camera number. The
     * suggested space is added with the spaces table (Phase 5, O-2).
     */
    public function up(): void
    {
        Schema::create('twin_anchors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('twin_model_id')->constrained()->cascadeOnDelete();
            $table->string('node_name', 120);
            $table->string('anchor_type', 20);
            $table->string('reference', 100);
            $table->timestamps();

            $table->unique(['twin_model_id', 'node_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('twin_anchors');
    }
};
