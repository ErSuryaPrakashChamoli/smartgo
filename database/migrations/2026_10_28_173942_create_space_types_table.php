<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The admin-managed physical classification of spaces (DATABASE_ERD §3, §3.1, C-4). The initial
     * types come from the reference seeder; they are not facts about any property.
     */
    public function up(): void
    {
        Schema::create('space_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('name', 80);
            $table->string('default_inventory_mode', 20);
            $table->json('allowed_inventory_modes');
            $table->boolean('is_bookable_by_time')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('space_types');
    }
};
