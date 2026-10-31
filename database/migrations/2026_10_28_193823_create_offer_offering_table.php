<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Which offerings an offer applies to (DATABASE_ERD §4, `offer_offering`).
     */
    public function up(): void
    {
        Schema::create('offer_offering', function (Blueprint $table) {
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offering_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->primary(['offer_id', 'offering_id']);
            $table->index('offering_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offer_offering');
    }
};
