<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * What validation found in an import, row by row (INVENTORY_IMPORT_SPEC §4, §5). Values are truncated
     * and personal data in them is masked.
     */
    public function up(): void
    {
        Schema::create('inventory_import_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_import_id')->constrained()->cascadeOnDelete();
            $table->string('sheet', 40);
            $table->unsignedInteger('row')->nullable();
            $table->string('column', 60)->nullable();
            $table->string('rule', 10);
            $table->string('severity', 10);
            $table->string('message', 500);
            $table->string('value_excerpt', 80)->nullable();
            $table->timestamps();

            $table->index(['inventory_import_id', 'severity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_import_issues');
    }
};
