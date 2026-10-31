<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The workspace categories a space can be sold as (DATABASE_ERD §3, C-6). Availability stays on the
     * space, so any use blocks every other (BR-AV-3c). The offering's property must be the space's,
     * which the action that saves a space checks.
     */
    public function up(): void
    {
        Schema::create('offering_space', function (Blueprint $table) {
            $table->foreignId('offering_id')->constrained()->restrictOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['offering_id', 'space_id']);
            $table->index('space_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offering_space');
    }
};
