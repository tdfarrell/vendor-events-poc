<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Every organizer-owned table carries organizer_id, even where it could be
 * reached through events, so the tenant scope can filter each table directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained();
            $table->string('type', 40);                       // key in config/event_types.php
            $table->string('slug', 120);
            $table->string('title', 200);
            $table->date('starts_on')->nullable();
            $table->string('hours', 80)->default('');
            $table->string('vendor_setup', 120)->default('');
            $table->string('venue_name', 200)->default('');
            $table->string('venue_address', 255)->default('');
            $table->string('town', 100)->default('');
            $table->text('description')->nullable();
            $table->string('admission_note', 200)->default('');
            $table->string('categories', 500)->default('');   // comma separated
            $table->text('vendor_terms')->nullable();
            $table->string('status', 20)->default('draft');   // draft | published | archived
            $table->string('notice', 20)->default('');        // '' | cancelled | rescheduled
            $table->string('notice_text', 500)->default('');
            $table->boolean('applications_open')->default(false);
            $table->string('vendor_payment_mode', 20)->default('review'); // review | instant | approve_first
            $table->unsignedSmallInteger('payment_window_days')->default(7);
            $table->boolean('tickets_on_sale')->default(false);
            $table->unsignedInteger('ticket_capacity')->nullable();
            $table->string('checkin_token', 32)->nullable()->unique();
            $table->timestamps();

            $table->unique(['organizer_id', 'slug']);
            $table->index(['status', 'starts_on']);
        });

        Schema::create('table_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('description', 255)->default('');
            $table->unsignedInteger('price_cents');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedSmallInteger('max_per_vendor')->default(2);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // One account per vendor across the whole platform.
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->unique();
            $table->string('slug', 120)->nullable()->unique();
            $table->string('business_name', 200)->default('');
            $table->string('contact_name', 150)->default('');
            $table->string('phone', 40)->default('');
            $table->string('town', 100)->default('');
            $table->text('description')->nullable();
            $table->string('categories', 500)->default('');
            $table->string('instagram', 255)->default('');
            $table->string('facebook', 255)->default('');
            $table->string('website', 255)->default('');
            $table->boolean('listed')->default(true);         // vendor chose to appear in the directory
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('vendor_login_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->index();
            $table->string('token_hash', 64)->unique();
            $table->string('ip', 64)->default('');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('vendor_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained();
            $table->foreignId('event_id')->constrained();
            $table->foreignId('table_type_id')->constrained();
            $table->foreignId('vendor_id')->nullable()->constrained();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('business_name', 200);
            $table->string('contact_name', 150);
            $table->string('email', 190)->index();
            $table->string('phone', 40)->default('');
            $table->string('categories', 500)->default('');
            $table->text('inventory_description')->nullable();
            $table->string('social_link', 255)->default('');
            $table->boolean('needs_power')->default(false);
            $table->text('vendor_notes')->nullable();
            // checkout | incomplete | pending | approved | paid | waitlisted | declined | cancelled
            $table->string('status', 20)->default('pending');
            $table->text('admin_note')->nullable();
            $table->string('table_numbers', 60)->default('');
            $table->string('pay_token', 40)->unique();
            $table->unsignedInteger('amount_cents')->nullable();
            $table->unsignedInteger('platform_fee_cents')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('pay_by')->nullable();
            $table->timestamp('hold_until')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 20)->nullable();  // online | cash | other
            $table->string('payment_reference')->nullable();
            $table->unsignedInteger('refunded_cents')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['table_type_id', 'status']);
        });

        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('description', 255)->default('');
            $table->unsignedInteger('price_cents')->default(0);
            $table->unsignedInteger('quantity');
            $table->unsignedSmallInteger('max_per_order')->default(10);
            $table->boolean('active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ticket_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained();
            $table->foreignId('event_id')->constrained();
            $table->string('buyer_name', 150);
            $table->string('email', 190)->index();
            $table->string('status', 20)->default('pending');  // pending | paid | expired | cancelled
            $table->unsignedInteger('subtotal_cents')->default(0);
            $table->unsignedInteger('fee_cents')->default(0);   // platform ticket fee, paid by buyer
            $table->unsignedInteger('total_cents')->default(0);
            $table->string('payment_method', 20)->nullable();   // online | free | comp
            $table->string('payment_reference')->nullable();
            $table->string('order_token', 40)->unique();
            $table->timestamp('hold_until')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('admin_note', 500)->default('');
            $table->timestamps();

            $table->index(['event_id', 'status']);
        });

        Schema::create('ticket_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained();
            $table->foreignId('ticket_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained();
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('unit_price_cents');
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained();
            $table->foreignId('ticket_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained();
            $table->foreignId('ticket_type_id')->constrained();
            $table->string('code', 12)->unique();
            $table->string('status', 10)->default('valid');     // valid | void
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
        });

        Schema::create('door_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10)->default('sale');        // sale | kids
            $table->foreignId('ticket_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label', 120);
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('amount_cents');
            $table->string('method', 10)->default('cash');      // cash | card | other
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['door_sales', 'tickets', 'ticket_order_items', 'ticket_orders', 'ticket_types', 'vendor_applications',
            'vendor_login_tokens', 'vendors', 'table_types', 'events'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
