<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The listed reason a lost or disqualified enquiry was closed for (BR-CRM-5). `closed_reason` keeps
     * the optional details written with it.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('close_reason_id')->nullable()->after('first_responded_at')->constrained('lead_close_reasons')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('close_reason_id');
        });
    }
};
