<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A deal opened from an enquiry (DATABASE_ERD §6 `deals`, FR-CRM-4). Seats, the monthly value (paise) and
     * the expected close date are what staff type, each optional. A deal is won or lost once, with the
     * agreement reference its allocations carry (BR-CRM-7) or the reason it was lost; it is never deleted.
     * The ERD's `company_id` waits for the companies table, which is not built.
     */
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->restrictOnDelete();
            $table->foreignId('pipeline_stage_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('seats')->nullable();
            $table->unsignedBigInteger('monthly_value_minor')->nullable();
            $table->date('expected_close_on')->nullable();
            $table->string('agreement_reference', 60)->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->string('lost_reason', 300)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['pipeline_stage_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
