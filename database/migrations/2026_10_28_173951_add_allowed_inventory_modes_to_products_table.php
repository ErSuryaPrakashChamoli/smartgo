<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The inventory modes each of the owner's seven categories can be sold with (BR-PRODCFG-2); a
     * virtual office has no physical space (BR-PRODCFG-3).
     *
     * @var array<string, list<string>>
     */
    private const array MODES = [
        'private_office' => ['exclusive'],
        'dedicated_desk' => ['per_seat', 'hybrid'],
        'hot_desk' => ['pooled', 'per_seat', 'hybrid'],
        'meeting_room' => ['exclusive'],
        'conference_room' => ['exclusive'],
        'virtual_office' => [],
        'managed_office' => ['exclusive'],
    ];

    /**
     * Run the migrations.
     *
     * Which inventory modes a workspace category can be sold with (DATABASE_ERD §4.1,
     * `allowed_inventory_modes`). Empty means the category uses no physical space; null means staff
     * have not set it yet, so no space can be sold as that category.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('allowed_inventory_modes')->nullable()->after('is_active');
        });

        foreach (self::MODES as $key => $modes) {
            DB::table('products')->where('key', $key)->whereNull('allowed_inventory_modes')->update(['allowed_inventory_modes' => json_encode($modes)]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('allowed_inventory_modes');
        });
    }
};
