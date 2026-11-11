<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The highlights in the moving strip under the home page's banners (OD-169), in `sort_order`: a short line and an
     * icon each. `is_sample` marks example text that SmartGro has not confirmed; the strip then says so, and production
     * refuses to publish it (OD-149). The row holds only the published version; drafts and their review are revisions
     * (ADR-0005), and nothing is public until a version is approved and published (BR-CMS-1).
     */
    public function up(): void
    {
        Schema::create('home_highlights', function (Blueprint $table) {
            $table->id();
            $table->string('text', 80)->nullable();
            $table->string('icon', 40)->nullable();
            $table->boolean('is_sample')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('draft');
            $table->foreignId('published_revision_id')->nullable()->constrained('revisions')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('home_highlights');
    }
};
