<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Each organizer is one customer of the platform (one subdomain).
        Schema::create('organizers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 63)->unique();      // subdomain
            $table->string('tagline', 200)->default('');
            $table->string('contact_email', 190)->default('');
            $table->string('email_from_name', 150)->default('');
            $table->string('brand_color', 7)->default('#2f5d50');
            $table->string('town', 100)->default('');
            $table->string('website', 255)->default('');
            $table->string('payment_account_id')->nullable(); // Stripe Connect account later
            $table->timestamps();
        });

        // Organizer staff. Vendors and attendees are not users.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained();
            $table->string('role', 20)->default('owner'); // owner | staff
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('organizers');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
