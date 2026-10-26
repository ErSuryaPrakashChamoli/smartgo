<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Live redirects only (ADR-0005, OD-85): a row exists only once its change has been approved and
     * published. Step 4.2 creates them when a published page changes its address (BR-CMS-6); redirects
     * made by hand go through revisions in step 4.6 (OD-66, BR-CMS-9).
     */
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path');
            $table->string('match_type', 10)->default('exact');
            $table->string('to_path')->nullable();
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['from_path', 'match_type']);
            $table->index('to_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
