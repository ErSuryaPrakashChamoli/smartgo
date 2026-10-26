<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Frequently asked questions (FR-CMS-7), published at /faqs. Property and product scopes arrive with Phase 5. The row holds only the published version; drafts and their review are revisions
     * (ADR-0005), and nothing is public until a version is approved and published (BR-CMS-1).
     */
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question', 150)->nullable();
            $table->text('answer')->nullable();
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
        Schema::dropIfExists('faqs');
    }
};
