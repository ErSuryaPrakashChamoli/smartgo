<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The property an image shows (DATABASE_ERD `depicts_property_id`). Set at upload and never changed,
     * so a property's photos can only be chosen for that property, and a Property Manager handles only
     * the photos of their assigned properties. Null for images that show no particular property.
     */
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            $table->foreignId('depicts_property_id')->nullable()->after('origin')->constrained('properties')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('depicts_property_id');
        });
    }
};
