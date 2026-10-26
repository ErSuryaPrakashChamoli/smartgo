<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The row holds only the published page (ADR-0005); drafts and their review live in `revisions`.
     * Title and slug stay empty until the first version is published.
     */
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('slug')->nullable()->unique();
            $table->string('template', 20)->nullable();
            $table->json('blocks')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('published_revision_id')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
