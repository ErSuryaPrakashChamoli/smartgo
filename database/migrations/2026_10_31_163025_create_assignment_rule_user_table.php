<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The people an assignment rule may give enquiries to, in the order the Super Admin set (`user_ids` in
     * DATABASE_ERD §6, as rows so each person is a real staff account): the round-robin order and the
     * tie-breaker for least loaded. A fixed-person rule has exactly one.
     */
    public function up(): void
    {
        Schema::create('assignment_rule_user', function (Blueprint $table) {
            $table->foreignId('assignment_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->primary(['assignment_rule_id', 'user_id']);
            $table->unique(['assignment_rule_id', 'position']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_rule_user');
    }
};
