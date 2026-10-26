<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Insights articles (FR-CMS-7), published at /insights/{slug}. Categories and topic pages come later. The row holds only the published version; drafts and their review are revisions
     * (ADR-0005), and nothing is public until a version is approved and published (BR-CMS-1).
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120)->nullable();
            $table->string('slug', 150)->nullable()->unique();
            $table->string('excerpt', 300)->nullable();
            $table->json('blocks')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('published_revision_id')->nullable()->constrained('revisions')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
