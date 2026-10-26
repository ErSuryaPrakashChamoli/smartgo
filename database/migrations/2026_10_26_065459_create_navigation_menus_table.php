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
     * The website's fixed menus (FR-CMS-4). A row holds only the published menu; changes are revisions
     * (ADR-0005; navigation is publishable content, SECURITY_MODEL §11.5). The four menus are created
     * empty, so nothing appears on the website until a menu version is approved and published.
     */
    public function up(): void
    {
        Schema::create('navigation_menus', function (Blueprint $table) {
            $table->id();
            $table->string('handle', 30)->unique();
            $table->string('name', 60);
            $table->foreignId('published_revision_id')->nullable()->constrained('revisions')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('navigation_menus')->insert([
            ['handle' => 'main', 'name' => 'Main menu', 'created_at' => $now, 'updated_at' => $now],
            ['handle' => 'actions', 'name' => 'Header buttons', 'created_at' => $now, 'updated_at' => $now],
            ['handle' => 'footer', 'name' => 'Footer', 'created_at' => $now, 'updated_at' => $now],
            ['handle' => 'legal', 'name' => 'Legal links', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('navigation_menus');
    }
};
