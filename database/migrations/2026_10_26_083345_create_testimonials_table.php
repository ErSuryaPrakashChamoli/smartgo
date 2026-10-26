<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Customer testimonials (FR-CMS-7). A version is published only with consent on file and a verified statement (BR-CMS-7). The property link arrives with Phase 5. The row holds only the published version; drafts and their review are revisions
     * (ADR-0005), and nothing is public until a version is approved and published (BR-CMS-1).
     */
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->text('quote')->nullable();
            $table->string('person_name', 80)->nullable();
            $table->string('person_title', 80)->nullable();
            $table->string('company_name', 120)->nullable();
            $table->boolean('consent_on_file')->default(false);
            $table->string('consent_reference', 120)->nullable();
            $table->boolean('statement_verified')->default(false);
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
        Schema::dropIfExists('testimonials');
    }
};
