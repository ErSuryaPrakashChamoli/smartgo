<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A location's "Getting here" details (FR-DISC-7, Phase 6, OD-113): the nearest metro station and
     * the walk from it, buses, parking and accessibility, entered once per location and shown only once
     * a profile version with them is approved and published. Empty until SmartGro supplies them.
     */
    public function up(): void
    {
        Schema::table('property_profiles', function (Blueprint $table) {
            $table->string('nearest_metro', 80)->nullable()->after('getting_there');
            $table->unsignedTinyInteger('metro_walk_minutes')->nullable()->after('nearest_metro');
            $table->string('buses', 200)->nullable()->after('metro_walk_minutes');
            $table->string('parking', 300)->nullable()->after('buses');
            $table->string('accessibility', 300)->nullable()->after('parking');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_profiles', function (Blueprint $table) {
            $table->dropColumn(['nearest_metro', 'metro_walk_minutes', 'buses', 'parking', 'accessibility']);
        });
    }
};
