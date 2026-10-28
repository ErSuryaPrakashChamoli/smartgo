<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The managed lists of reasons for marking an enquiry lost or disqualified (BR-CRM-5, FR-CRM-5). A
     * reason in use is switched off rather than deleted, so closed enquiries keep theirs.
     */
    public function up(): void
    {
        Schema::create('lead_close_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20);
            $table->string('label', 80);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['status', 'label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_close_reasons');
    }
};
