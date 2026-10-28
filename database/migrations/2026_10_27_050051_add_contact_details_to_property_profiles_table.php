<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A property's own phone, WhatsApp number and email (round 39 decision 2), shown on its pages only
     * once a profile version with them is approved and published. Empty means the page shows SmartGro's
     * main published contact details.
     */
    public function up(): void
    {
        Schema::table('property_profiles', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('getting_there');
            $table->string('whatsapp', 16)->nullable()->after('phone');
            $table->string('email', 120)->nullable()->after('whatsapp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_profiles', function (Blueprint $table) {
            $table->dropColumn(['phone', 'whatsapp', 'email']);
        });
    }
};
