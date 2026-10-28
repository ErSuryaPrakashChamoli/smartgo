<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The marketing face of a property (FR-CMS-6, DATABASE_ERD §7): headline, summary, directions and
     * photos. It never holds capacity, status, availability or price. The row holds only the published
     * version; drafts and their review are revisions (ADR-0005). The gallery is an ordered list of
     * media asset IDs.
     */
    public function up(): void
    {
        Schema::create('property_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->unique()->constrained()->restrictOnDelete();
            $table->string('headline', 120)->nullable();
            $table->text('summary')->nullable();
            $table->text('getting_there')->nullable();
            $table->foreignId('hero_asset_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->json('gallery_asset_ids')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('published_revision_id')->nullable()->constrained('revisions')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_profiles');
    }
};
