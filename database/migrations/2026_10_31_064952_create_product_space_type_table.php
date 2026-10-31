<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Which space types each workspace category can be sold with (DATABASE_ERD §4.1 `product_space_type`,
     * BR-PRODCFG-2, ADR-0013): a space is sold as a category only when the category accepts both its type
     * and its inventory mode. Admin-managed reference data, kept on the category's form; the proposed
     * defaults (ERD §3.1) come from the reference seeder, not from this migration.
     */
    public function up(): void
    {
        Schema::create('product_space_type', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_type_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['product_id', 'space_type_id']);
            $table->index('space_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_space_type');
    }
};
