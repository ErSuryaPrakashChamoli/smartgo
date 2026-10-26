<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Public settings (BR-CMS-9, FR-CMS-8), one row per group. A row holds only the published values;
     * changes are revisions approved and published by different people (ADR-0005, OD-64, OD-67, OD-71,
     * OD-55). The groups are created empty: no contact detail, link, ID or legal detail is invented.
     */
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('handle', 20)->unique();
            $table->json('values')->nullable();
            $table->foreignId('published_revision_id')->nullable()->constrained('revisions')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('site_settings')->insert(array_map(
            fn (string $handle): array => ['handle' => $handle, 'created_at' => $now, 'updated_at' => $now],
            ['contact', 'social', 'tracking', 'legal'],
        ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
