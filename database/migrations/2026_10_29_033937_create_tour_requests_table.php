<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Site visits (DATABASE_ERD §5, FR-BK-2): who asked, for which property and time, how many people,
     * and where the request stands. A requested or confirmed tour holds its place in the slot (BR-TR-1);
     * every one belongs to an enquiry (BR-TR-3). Never deleted: cancellations keep their reason.
     */
    public function up(): void
    {
        Schema::create('tour_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('lead_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->dateTime('scheduled_start_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('status', 20);
            $table->string('channel', 20);
            $table->unsignedTinyInteger('visitor_count');
            $table->text('notes')->nullable();
            $table->foreignId('host_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancellation_reason', 300)->nullable();
            $table->timestamps();

            $table->index(['property_id', 'scheduled_start_at']);
            $table->index(['status', 'scheduled_start_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tour_requests');
    }
};
