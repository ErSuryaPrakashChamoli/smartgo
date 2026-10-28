<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Enquiries (DATABASE_ERD §8 `leads`, BR-CRM-1). An enquiry never reserves anything and never becomes
     * a booking or a payment. The property, category and offering are stored by ID, checked against
     * what was published when it was submitted. Every reference restricts deletion, so no enquiry or its
     * history disappears with another record. `consent_snapshot` records the notice and wording the
     * visitor agreed to.
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->string('source', 30);
            $table->string('status', 20)->default('new');
            $table->string('submitted_name', 120);
            $table->string('submitted_email', 191)->nullable();
            $table->string('submitted_phone', 20)->nullable();
            $table->string('submitted_company', 120)->nullable();
            $table->foreignId('city_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('offering_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('customer_segment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('team_size', 10)->nullable();
            $table->text('message')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('first_responded_at')->nullable();
            $table->string('closed_reason', 500)->nullable();
            $table->string('utm_source', 120)->nullable();
            $table->string('utm_medium', 120)->nullable();
            $table->string('utm_campaign', 120)->nullable();
            $table->string('utm_term', 120)->nullable();
            $table->string('utm_content', 120)->nullable();
            $table->string('landing_path', 255)->nullable();
            $table->json('consent_snapshot');
            $table->timestamps();

            $table->index(['status', 'owner_user_id']);
            $table->index(['property_id', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
