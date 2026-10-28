<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 3D models of a property, uploaded as GLB files that passed the automated checks (ERD §8, FR-3D-1,
     * BR-3D-4). The file is kept on the private disk through the media library package; the capture type
     * is set at upload and never changes, so a model's notice cannot be lost (BR-3D-2). The floor a model
     * shows is added with the floors table (Phase 5, O-2).
     */
    public function up(): void
    {
        Schema::create('twin_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('title', 120);
            $table->string('capture_type', 30);
            $table->date('captured_on')->nullable();
            $table->string('captured_by', 120)->nullable();
            $table->unsignedBigInteger('file_bytes');
            $table->unsignedInteger('triangle_count');
            $table->json('validation_report');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('twin_models');
    }
};
