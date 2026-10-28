<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Physical buildings of a property, which hold the address (DATABASE_ERD §3, Q-OW-2). Each property
     * has one primary building; the postal code and coordinates stay empty until supplied (OD-08).
     */
    public function up(): void
    {
        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name', 80)->nullable();
            $table->string('address_line_1', 120);
            $table->string('address_line_2', 120)->nullable();
            $table->string('locality', 80);
            $table->string('postal_code', 10)->nullable();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->string('state', 60)->nullable();
            $table->char('country_code', 2)->default('IN');
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['property_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buildings');
    }
};
