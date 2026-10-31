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
     * Back-office security settings (OD-124; ERD C-32): one row, created here. Two-step login starts
     * optional; a Super Admin makes it compulsory under Settings → Security, audited.
     */
    public function up(): void
    {
        Schema::create('security_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('two_step_login_required')->default(false);
            $table->timestamps();
        });

        DB::table('security_settings')->insert(['two_step_login_required' => false, 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_settings');
    }
};
