<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Live search-engine metadata of published content (FR-SEO-1, DATABASE_ERD §7). Values change only
     * through the content's approved and published revision ("page metadata follows its page's
     * approval", SECURITY_MODEL §3.1). The canonical URL is derived from the published address; the
     * Open Graph image (media library, step 4.4) and structured-data overrides (Phase 11) are added later.
     */
    public function up(): void
    {
        Schema::create('seo_metadata', function (Blueprint $table) {
            $table->id();
            $table->morphs('seoable');
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 200)->nullable();
            $table->string('robots', 40)->default('index, follow');
            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_metadata');
    }
};
