<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The spaces the website lists under Spaces in the main menu (OD-177): one row per space, at a location and of a
     * space type, with its own page at /spaces/{slug}. `space_id` optionally ties a listing to a space in the
     * inventory (the canonical record, which this table never changes); `is_sample` marks example content that
     * SmartGro has not confirmed. The row holds only the published version; drafts and their review are revisions
     * (ADR-0005), nothing is public until a version is approved and published (BR-CMS-1), and a listing can be taken
     * off the website at once (OD-98).
     */
    public function up(): void
    {
        Schema::create('space_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('space_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('space_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 80)->nullable();
            $table->string('slug', 120)->nullable()->unique();
            $table->string('summary', 200)->nullable();
            $table->text('details')->nullable();
            $table->unsignedSmallInteger('seats')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('draft');
            $table->foreignId('published_revision_id')->nullable()->constrained('revisions')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'space_type_id', 'property_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('space_listings');
    }
};
