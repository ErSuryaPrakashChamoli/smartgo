<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Market cities (DATABASE_ERD §3, OD-03, OD-16). "Operating" is derived from open properties, never
     * stored; a city without one is public only when switched visible (OD-18).
     */
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 60)->unique();
            $table->json('aliases')->nullable();
            $table->string('state', 60)->nullable();
            $table->char('country_code', 2)->default('IN');
            $table->boolean('is_target_market')->default(true);
            $table->boolean('is_publicly_visible')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
