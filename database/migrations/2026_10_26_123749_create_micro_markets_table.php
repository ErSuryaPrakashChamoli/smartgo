<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Business districts or sectors within a city, such as "Sector 62" (DATABASE_ERD §3).
     */
    public function up(): void
    {
        Schema::create('micro_markets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->string('slug', 80);
            $table->timestamps();

            $table->unique(['city_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('micro_markets');
    }
};
