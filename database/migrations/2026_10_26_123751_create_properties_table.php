<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SmartGro-operated properties (DATABASE_ERD §3, OD-08, OD-13). The operating model is always
     * recorded, never inferred (OD-09). Addresses live on buildings; floors, spaces, capacity and
     * prices come later from real inventory, never from defaults.
     */
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->foreignId('micro_market_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name', 80);
            $table->string('slug', 80);
            $table->string('timezone', 40)->default('Asia/Kolkata');
            $table->string('operating_model', 20);
            $table->string('operational_status', 20)->default('planned');
            $table->date('opens_on')->nullable();
            $table->timestamps();

            $table->unique(['city_id', 'slug']);
            $table->index('operational_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
