<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Venues: halls, posts, churches, schools and clubs list their rooms and take
 * rental inquiries. A venue with no organizer is a directory listing the
 * platform seeded; once claimed it belongs to that organizer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->nullable()->constrained();   // null = unclaimed listing
            $table->string('slug', 120)->unique();
            $table->string('name', 200);
            $table->string('kind', 40)->default('event_hall');               // config/venues.php kinds
            $table->string('address', 255)->default('');
            $table->string('town', 100)->default('');
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->text('about')->nullable();
            $table->text('rules')->nullable();                               // alcohol, catering, insurance, end time
            $table->string('parking_info', 500)->default('');
            $table->string('contact_email', 190)->default('');              // where inquiries go
            $table->string('phone', 40)->default('');
            $table->string('website', 255)->default('');
            $table->string('cover_path', 255)->default('');
            $table->string('status', 20)->default('active');                // listed | active | hidden
            $table->timestamps();
            $table->index(['status', 'town']);
        });

        Schema::create('venue_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->unsignedInteger('seated')->nullable();
            $table->unsignedInteger('standing')->nullable();
            $table->unsignedInteger('price_from')->nullable();              // whole dollars
            $table->unsignedInteger('price_to')->nullable();
            $table->string('price_unit', 20)->default('event');             // event | hour | day
            $table->text('description')->nullable();
            $table->json('amenities')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('venue_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('email', 190);
            $table->string('phone', 40)->default('');
            $table->string('event_kind', 40)->default('other');
            $table->date('event_date')->nullable();
            $table->boolean('date_flexible')->default(false);
            $table->string('time_range', 80)->default('');
            $table->unsignedInteger('guests')->nullable();
            $table->string('budget', 80)->default('');
            $table->text('message')->nullable();
            $table->string('status', 20)->default('new');                   // new | replied | booked | declined | closed
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
            $table->index(['venue_id', 'status']);
        });

        Schema::create('venue_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('email', 190);
            $table->string('phone', 40)->default('');
            $table->string('role', 150)->default('');
            $table->text('note')->nullable();
            $table->string('status', 20)->default('new');                   // new | approved | rejected
            $table->timestamps();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venue_id');
        });
        Schema::dropIfExists('venue_claims');
        Schema::dropIfExists('venue_inquiries');
        Schema::dropIfExists('venue_rooms');
        Schema::dropIfExists('venues');
    }
};
