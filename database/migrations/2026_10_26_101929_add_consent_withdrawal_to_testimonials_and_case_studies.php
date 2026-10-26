<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Records on the item itself, not on a version, that its consent was withdrawn (OD-101), so the
     * record holds across every version until consent is explicitly re-established. Existing rows stay
     * null: their consent is unchanged.
     */
    public function up(): void
    {
        foreach (['testimonials', 'case_studies'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->timestamp('consent_withdrawn_at')->nullable()->after('consent_reference');
                $table->foreignId('consent_withdrawn_by')->nullable()->after('consent_withdrawn_at')->constrained('users')->restrictOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['testimonials', 'case_studies'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('consent_withdrawn_by');
                $table->dropColumn('consent_withdrawn_at');
            });
        }
    }
};
