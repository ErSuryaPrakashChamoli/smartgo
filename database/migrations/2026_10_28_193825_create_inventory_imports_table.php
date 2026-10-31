<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One uploaded SG-INV workbook and its lifecycle (INVENTORY_IMPORT_SPEC §3, §5; ADR-0012): validated,
     * previewed, submitted by its uploader, approved by someone else, then committed all or nothing. The
     * file stays on the private disk with its checksum.
     */
    public function up(): void
    {
        Schema::create('inventory_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('original_name', 190);
            $table->string('file_path', 255);
            $table->char('file_sha256', 64);
            $table->string('template_id', 20)->nullable();
            $table->string('status', 20);
            $table->json('summary')->nullable();
            $table->char('plan_fingerprint', 64)->nullable();
            $table->foreignId('warnings_acknowledged_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('warnings_acknowledged_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->string('environment', 20);
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('file_sha256');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_imports');
    }
};
