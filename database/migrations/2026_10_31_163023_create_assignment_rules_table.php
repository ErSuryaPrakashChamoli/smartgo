<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Rules that give a new enquiry an owner (DATABASE_ERD §6 `assignment_rules`, FR-CRM-2, BR-CRM-3), kept by
     * the Super Admin (OD-36). The first matching active rule decides, lowest priority number first; each
     * priority is used once. `criteria` holds the chosen cities, locations, workspace types, segments, team
     * sizes and sources (an empty list means any). `last_assigned_user_id` is the round-robin turn. Rules
     * start inactive and are never deleted; none is created by default.
     */
    public function up(): void
    {
        Schema::create('assignment_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->unsignedInteger('priority')->unique();
            $table->string('strategy', 20);
            $table->json('criteria');
            $table->boolean('is_active')->default(false);
            $table->foreignId('last_assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_rules');
    }
};
