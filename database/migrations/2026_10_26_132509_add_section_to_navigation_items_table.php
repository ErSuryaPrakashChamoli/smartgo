<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Lets a menu item link to a fixed part of the website, such as the locations list, by key rather
     * than by a typed address (FR-CMS-4). Links to a property use the existing `linkable` columns.
     */
    public function up(): void
    {
        Schema::table('navigation_items', function (Blueprint $table) {
            $table->string('section', 30)->nullable()->after('url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('navigation_items', function (Blueprint $table) {
            $table->dropColumn('section');
        });
    }
};
