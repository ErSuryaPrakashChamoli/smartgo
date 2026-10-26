<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The media library (ADR-0006, FR-CMS-5, BR-MED-1 to BR-MED-5). Each asset owns one file through
     * spatie/laravel-medialibrary. The property link (`depicts_property_id`, `is_real_location_capture`)
     * and the `mediables` pivot for galleries arrive with properties in Phase 5.
     */
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('alt_text', 150)->nullable();
            $table->boolean('is_decorative')->default(false);
            $table->string('caption', 200)->nullable();
            $table->decimal('focal_point_x', 4, 3)->default(0.5);
            $table->decimal('focal_point_y', 4, 3)->default(0.5);
            $table->string('origin', 30);
            $table->string('licence_type', 40)->nullable();
            $table->string('licence_holder', 120)->nullable();
            $table->string('licence_reference', 120)->nullable();
            $table->date('licence_expires_at')->nullable();
            $table->timestamp('licence_reminder_sent_at')->nullable();
            $table->string('credit_line', 150)->nullable();
            $table->text('usage_restrictions')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('licence_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
