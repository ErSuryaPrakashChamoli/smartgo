<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Whether a Sales/CRM Executive works on every property. Off by default: a Sales/CRM Executive sees
     * only the properties assigned to them (and their enquiries) until the Super Admin decides
     * otherwise for that person (OD-36's optional restriction, applied by default per the Phase 5
     * directive). It has no effect on other roles.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('has_all_property_access')->default(false)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('has_all_property_access');
        });
    }
};
