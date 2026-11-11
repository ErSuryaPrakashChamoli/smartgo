<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A listed space's photo from the media library (OD-177 e): photography of its location, or, where sample content
     * is allowed, a licensed stock photo that the page labels as not the location. Like a home banner's image, the
     * reference is cleared if the asset is ever removed; the media library refuses to remove one in use.
     */
    public function up(): void
    {
        Schema::table('space_listings', function (Blueprint $table) {
            $table->foreignId('media_asset_id')->nullable()->after('features')->constrained('media_assets')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('space_listings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('media_asset_id');
        });
    }
};
