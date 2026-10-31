<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Promotional offers (DATABASE_ERD §4, FR-INV-5, PRICING_CONFIGURATION_PROPOSAL §7): a percentage,
     * a fixed amount or a free period, valid between two instants, with mandatory terms (BR-PR-5). An
     * offer is active only after another person approves it, and stops applying at its end without any
     * job: its expiry is derived from `ends_at`. None is seeded.
     */
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 40)->nullable()->unique();
            $table->string('discount_type', 20);
            $table->unsignedSmallInteger('percent_bps')->nullable();
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->unsignedSmallInteger('free_period_quantity')->nullable();
            $table->string('free_period_unit', 10)->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->text('terms');
            $table->string('status', 20)->default('draft');
            $table->json('editor_ids');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
