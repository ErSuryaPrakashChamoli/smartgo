<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The topics of an article's published version (C-31): an ordered list of `post_topics` ids, like a
     * profile's gallery. Set through the article's versions, so a topic appears on an article only once
     * a version with it is approved and published (BR-CMS-1).
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->json('topic_ids')->nullable()->after('blocks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('topic_ids');
        });
    }
};
