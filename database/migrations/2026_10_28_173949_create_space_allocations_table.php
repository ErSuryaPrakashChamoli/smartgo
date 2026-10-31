<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Long-term occupancy of a space or a seat (DATABASE_ERD §3, FR-INV-3). Allocations are never
     * deleted: they end or are cancelled. The company and deal links join with the CRM (Phase 9); until
     * then an allocation is identified by its internal agreement reference.
     */
    public function up(): void
    {
        Schema::create('space_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained()->restrictOnDelete();
            $table->foreignId('space_seat_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('agreement_reference', 60);
            $table->unsignedSmallInteger('seats_allocated');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('notice_given_on')->nullable();
            $table->string('status', 20);
            $table->string('source', 20)->default('manual');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['space_id', 'status', 'starts_on', 'ends_on']);
            $table->index('agreement_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('space_allocations');
    }
};
