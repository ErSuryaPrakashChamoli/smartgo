<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The marketing face of one workspace offering at one property (FR-CMS-6, DATABASE_ERD §7). It
     * never holds capacity, availability or price, which belong to inventory and pricing. The row holds
     * only the published version; drafts and their review are revisions (ADR-0005).
     */
    public function up(): void
    {
        Schema::create('offering_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offering_id')->unique()->constrained()->restrictOnDelete();
            $table->string('headline', 120)->nullable();
            $table->text('description')->nullable();
            $table->json('included')->nullable();
            $table->json('not_included')->nullable();
            $table->foreignId('hero_asset_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->json('gallery_asset_ids')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('published_revision_id')->nullable()->constrained('revisions')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offering_profiles');
    }
};
