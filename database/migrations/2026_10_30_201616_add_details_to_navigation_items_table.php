<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A short description and an icon for the links and categories inside a main-menu drop-down, shown
     * under each label (OD-149). Both are optional; the icon is a name from NavigationItem::ICONS.
     */
    public function up(): void
    {
        Schema::table('navigation_items', function (Blueprint $table) {
            $table->string('description', 160)->nullable()->after('label');
            $table->string('icon', 40)->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('navigation_items', function (Blueprint $table) {
            $table->dropColumn(['description', 'icon']);
        });
    }
};
