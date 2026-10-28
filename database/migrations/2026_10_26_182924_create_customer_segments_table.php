<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The eight customer segments (OD-04), an optional answer on enquiry forms (BR-CRM-1a). They carry
     * no priority until the owner sets one.
     */
    public function up(): void
    {
        Schema::create('customer_segments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 80)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('marketing_priority')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_segments');
    }
};
