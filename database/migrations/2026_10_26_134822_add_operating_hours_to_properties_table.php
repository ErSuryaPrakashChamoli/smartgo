<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Opening hours per weekday (DATABASE_ERD §3), entered by operations staff. Empty until SmartGro
     * confirms them; the website shows hours only once they are recorded.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->json('operating_hours')->nullable()->after('opens_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('operating_hours');
        });
    }
};
