<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Operational price truth (DATABASE_ERD §4, BR-PR-1), with the additions of the approved pricing
     * structure (PRICING_CONFIGURATION_PROPOSAL §12, OD-35): cabin and day-bundle units, the deposit rule
     * and the set-up fee. Money is integer paise in INR, stored tax-exclusive. No value is seeded: Finance
     * enters every price, and another person activates it (BR-PR-2).
     */
    public function up(): void
    {
        Schema::create('price_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offering_id')->constrained()->restrictOnDelete();
            $table->foreignId('space_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('unit', 20);
            $table->unsignedSmallInteger('bundle_size')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('INR');
            $table->string('tax_treatment', 10)->default('exclusive');
            $table->unsignedSmallInteger('min_term_months')->nullable();
            $table->string('deposit_type', 10)->nullable();
            $table->decimal('deposit_months', 5, 2)->nullable();
            $table->unsignedBigInteger('deposit_minor')->nullable();
            $table->unsignedBigInteger('setup_fee_minor')->nullable();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->string('status', 20)->default('draft');
            $table->json('editor_ids');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('retired_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->index(['offering_id', 'space_id', 'unit', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_plans');
    }
};
