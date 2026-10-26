<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per version of a publishable item (ADR-0005). Author, editors, approver and publisher are
     * recorded separately so the separation-of-duties rules can be enforced and audited (OD-65, OD-73, OD-85).
     */
    public function up(): void
    {
        Schema::create('revisions', function (Blueprint $table) {
            $table->id();
            $table->morphs('revisionable');
            $table->unsignedInteger('number');
            $table->json('payload');
            $table->string('state', 20);
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->json('editor_ids');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->foreignId('scheduled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['revisionable_type', 'revisionable_id', 'number']);
            $table->index(['state', 'scheduled_for']);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->foreign('published_revision_id')->references('id')->on('revisions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropForeign(['published_revision_id']);
        });

        Schema::dropIfExists('revisions');
    }
};
