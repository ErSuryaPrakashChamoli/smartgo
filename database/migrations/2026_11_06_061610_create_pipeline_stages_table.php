<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The stages a deal moves through (DATABASE_ERD §6 `pipeline_stages`, FR-CRM-4), kept by the Super Admin
     * (OD-36); none is created by default (D-09). `outcome` is `open`, `won` or `lost`, one column instead of
     * the ERD's `is_won` and `is_lost`, so a stage can never be both. `probability_pct` stays empty until the
     * Super Admin enters one. A stage in use is switched off, never deleted, so past deals keep it.
     */
    public function up(): void
    {
        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('probability_pct')->nullable();
            $table->string('outcome', 10)->default('open');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipeline_stages');
    }
};
