<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A location's 3D walkthrough and floor plans on its website page (OD-181, ERD C-43, FR-3D-3, BR-3D-5): one of the
     * location's 3D models, the guided views through it (its SG_CAM_ camera positions, in order, each with a title),
     * and its floor plans (a floor name, an image from the media library, a note, and whether it is an example).
     * They go live with the profile, through the same approval. Deleting a model a profile or a version uses is
     * refused (DeleteTwinModel); the reference is cleared if a model is ever removed another way.
     */
    public function up(): void
    {
        Schema::table('property_profiles', function (Blueprint $table) {
            $table->foreignId('twin_model_id')->nullable()->after('gallery_asset_ids')->constrained('twin_models')->nullOnDelete();
            $table->json('twin_tour_stops')->nullable()->after('twin_model_id');
            $table->json('floor_plans')->nullable()->after('twin_tour_stops');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('twin_model_id');
            $table->dropColumn(['twin_tour_stops', 'floor_plans']);
        });
    }
};
