<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Round two: photos, maps, floor plans, vendor messaging, email log,
 * off-platform directory listings, organizer sign-up and platform admins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            $table->string('status', 20)->default('active');    // pending | active
            $table->text('about')->nullable();
            $table->string('logo_path', 255)->default('');
            $table->string('phone', 40)->default('');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('cover_path', 255)->default('');
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->string('parking_info', 500)->default('');
            $table->json('amenities')->nullable();              // ["free_parking","food",...]
            $table->json('faq')->nullable();                    // [{q, a}, ...]
            $table->string('ends_at_time', 20)->default('');
            $table->foreignId('copied_from_id')->nullable();
        });

        Schema::create('event_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('path', 255);
            $table->string('caption', 200)->default('');
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->string('logo_path', 255)->default('');
        });

        Schema::create('vendor_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('path', 255);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('vendor_applications', function (Blueprint $table) {
            $table->string('table_notified', 60)->default('');  // table numbers last emailed
        });

        Schema::create('floor_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('width_ft', 6, 1)->default(60);
            $table->decimal('depth_ft', 6, 1)->default(40);
            $table->decimal('aisle_ft', 4, 1)->default(4);
            $table->longText('elements');
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        // Every email the platform sends, so organizers can see what went out.
        Schema::create('email_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->nullable()->constrained();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 40)->default('');            // booking | tickets | message | tables | login ...
            $table->string('to_email', 190)->index();
            $table->string('subject', 255);
            $table->text('body');
            $table->string('status', 20)->default('sent');
            $table->timestamps();
        });

        // Events run off the platform, listed in the directory until an organizer claims them.
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->string('title', 200);
            $table->date('starts_on')->nullable();
            $table->string('hours', 80)->default('');
            $table->string('venue_name', 200)->default('');
            $table->string('venue_address', 255)->default('');
            $table->string('town', 100)->default('');
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->string('host_name', 150)->default('');
            $table->string('source_url', 255)->default('');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('listed');    // listed | claimed | hidden
            $table->foreignId('claimed_by_organizer_id')->nullable()->constrained('organizers');
            $table->timestamps();
            $table->index(['status', 'starts_on']);
        });

        Schema::create('listing_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('email', 190);
            $table->string('phone', 40)->default('');
            $table->string('role', 150)->default('');
            $table->text('note')->nullable();
            $table->string('status', 20)->default('new');       // new | approved | rejected
            $table->timestamps();
        });

        Schema::create('platform_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['platform_admins', 'listing_claims', 'listings', 'email_log', 'floor_plans', 'vendor_photos', 'event_photos'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
