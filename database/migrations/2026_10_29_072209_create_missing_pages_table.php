<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Addresses on the website that answered "not found" (FR-SEO-4's 404 monitor): the path only, without
     * its query, how often and when it was asked for. Nothing about who asked: no IP address, browser or
     * referring page.
     */
    public function up(): void
    {
        Schema::create('missing_pages', function (Blueprint $table) {
            $table->id();
            $table->string('path', 255)->unique();
            $table->unsignedInteger('hits')->default(1);
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('missing_pages');
    }
};
