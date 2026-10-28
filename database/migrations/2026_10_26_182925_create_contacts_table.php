<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * People who contacted SmartGro (DATABASE_ERD §8). A new enquiry matches an existing contact on the
     * normalised email or the E.164 phone number and never overwrites the contact's details (BR-CRM-2);
     * what the visitor typed is kept on the enquiry. Contacts are never deleted by the application.
     */
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 191)->nullable()->index();
            $table->string('phone_e164', 20)->nullable()->index();
            $table->string('company_name', 120)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
