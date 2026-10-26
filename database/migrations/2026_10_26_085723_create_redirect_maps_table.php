<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The list of redirects made by hand (FR-SEO-4, FR-SEO-6), changed only through revisions approved
     * and published by different people (OD-66, BR-CMS-9). Its live redirects are the `redirects` rows
     * with source `manual`; it is created empty, so no old address is invented.
     */
    public function up(): void
    {
        Schema::create('redirect_maps', function (Blueprint $table) {
            $table->id();
            $table->string('handle', 20)->unique();
            $table->foreignId('published_revision_id')->nullable()->constrained('revisions')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        DB::table('redirect_maps')->insert(['handle' => 'manual', 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('redirect_maps');
    }
};
