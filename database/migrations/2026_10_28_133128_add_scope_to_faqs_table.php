<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Where a published FAQ is shown (FR-CMS-7: global, location and product scope; ERD C-33): with
     * neither, on the FAQ page; with a property, on that location's page; with a product, on that
     * workspace type's page. Set through the FAQ's versions. A property or product with FAQs cannot be
     * deleted, so a location's question never turns into a general one.
     */
    public function up(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->foreignId('property_id')->nullable()->after('sort_order')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->after('property_id')->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->dropConstrainedForeignId('property_id');
        });
    }
};
