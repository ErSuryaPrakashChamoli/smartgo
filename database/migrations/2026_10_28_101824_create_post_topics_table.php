<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Insight topics (IA /insights/topic/{slug}; ERD `post_categories`, C-31). Marketing keeps the list;
     * none is preset. A topic reaches the website only through an article published with it.
     */
    public function up(): void
    {
        Schema::create('post_topics', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('slug', 60)->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_topics');
    }
};
