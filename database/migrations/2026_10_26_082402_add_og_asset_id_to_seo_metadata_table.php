<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The image shown when a published page is shared (FR-SEO-1, DATABASE_ERD §7 `og_asset_id`).
     */
    public function up(): void
    {
        Schema::table('seo_metadata', function (Blueprint $table) {
            $table->foreignId('og_asset_id')->nullable()->after('robots')->constrained('media_assets')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seo_metadata', function (Blueprint $table) {
            $table->dropConstrainedForeignId('og_asset_id');
        });
    }
};
