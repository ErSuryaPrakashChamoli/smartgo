<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The published items of a menu, rebuilt from the menu's revision when it is published. Links to
     * pages are stored by ID, so a change of address never breaks a menu (FR-CMS-4); `group` items are
     * headings for a drop-down or footer column and hold no link.
     */
    public function up(): void
    {
        Schema::create('navigation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('navigation_menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('navigation_items')->cascadeOnDelete();
            $table->string('label', 60);
            $table->string('link_type', 20);
            $table->nullableMorphs('linkable');
            $table->string('url', 2048)->nullable();
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();

            $table->index(['navigation_menu_id', 'parent_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('navigation_items');
    }
};
