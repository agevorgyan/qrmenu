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
        // 1. Add event_inquiry_settings to vendors table if not exists
        if (! Schema::hasColumn('vendors', 'event_inquiry_settings')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->jsonb('event_inquiry_settings')->nullable()->after('reservation_notification_settings');
            });
        }

        // 2. Create event_inquiries table
        if (! Schema::hasTable('event_inquiries')) {
            Schema::create('event_inquiries', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('reference', 32)->unique()->index();
                $table->unsignedBigInteger('vendor_id')->index();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->unsignedBigInteger('assigned_user_id')->nullable()->index();

                // Foreign keys with explicit names
                $table->foreign('vendor_id', 'ev_inq_vendor_id_fk')->references('id')->on('vendors')->cascadeOnDelete();
                $table->foreign('location_id', 'ev_inq_location_id_fk')->references('id')->on('locations')->nullOnDelete();
                $table->foreign('assigned_user_id', 'ev_inq_assigned_user_id_fk')->references('id')->on('users')->nullOnDelete();

                // Event & Customer Details
                $table->string('event_type', 64)->index();
                $table->string('customer_name')->index();
                $table->string('customer_phone', 64)->index();
                $table->string('customer_email')->nullable()->index();
                $table->string('preferred_contact_method', 32)->default('phone'); // phone, whatsapp, email

                // Date & Time
                $table->date('event_date')->index();
                $table->string('start_time', 16)->nullable(); // e.g. "18:00"
                $table->string('end_time', 16)->nullable();   // e.g. "23:00"
                $table->decimal('estimated_duration_hours', 4, 1)->nullable();

                // Capacity & Budget
                $table->unsignedInteger('guest_count')->index();
                $table->decimal('budget', 12, 2)->nullable();
                $table->string('budget_range', 100)->nullable();
                $table->string('budget_currency', 10)->default('AMD');

                // Venue & Preferences
                $table->string('venue_arrangement', 32)->default('no_preference'); // indoor, outdoor, private_room, no_preference
                $table->text('food_beverage_preferences')->nullable();
                $table->jsonb('additional_services')->nullable(); // ['decoration', 'music', 'entertainment', 'equipment']
                $table->text('comments')->nullable();

                // Status & Administration
                // Statuses: new, contacted, preparing_offer, offer_sent, negotiation, confirmed, completed, declined, cancelled
                $table->string('status', 32)->default('new')->index();
                $table->decimal('quoted_price', 12, 2)->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->string('locale', 10)->default('hy');

                $table->timestamps();
                $table->softDeletes();

                // Tenant Isolation prerequisite
                $table->unique(['id', 'vendor_id'], 'event_inquiries_id_vendor_id_unique');

                // Composite indexes for fast tenant filtering & sorting
                $table->index(['vendor_id', 'status', 'event_date'], 'ev_inq_vendor_status_date_idx');
                $table->index(['vendor_id', 'created_at'], 'ev_inq_vendor_created_idx');
            });
        }

        // 3. Create event_inquiry_notes table
        if (! Schema::hasTable('event_inquiry_notes')) {
            Schema::create('event_inquiry_notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id')->index();
                $table->unsignedBigInteger('event_inquiry_id')->index();
                $table->unsignedBigInteger('user_id');
                $table->text('note');
                $table->timestamps();

                $table->foreign('vendor_id', 'ev_notes_vendor_id_fk')->references('id')->on('vendors')->cascadeOnDelete();
                $table->foreign('user_id', 'ev_notes_user_id_fk')->references('id')->on('users')->cascadeOnDelete();

                $table->index(['vendor_id', 'event_inquiry_id'], 'ev_notes_vendor_inq_idx');

                // Tenant composite foreign key constraint
                $table->foreign(['event_inquiry_id', 'vendor_id'], 'ev_notes_inq_vendor_fk')
                    ->references(['id', 'vendor_id'])
                    ->on('event_inquiries')
                    ->cascadeOnDelete();
            });
        }

        // 4. Create event_inquiry_activities table
        if (! Schema::hasTable('event_inquiry_activities')) {
            Schema::create('event_inquiry_activities', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id')->index();
                $table->unsignedBigInteger('event_inquiry_id')->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action', 64); // created, status_updated, assigned, note_added, quote_updated, details_updated
                $table->string('description', 255);
                $table->jsonb('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent()->index();

                $table->foreign('vendor_id', 'ev_activities_vendor_id_fk')->references('id')->on('vendors')->cascadeOnDelete();
                $table->foreign('user_id', 'ev_activities_user_id_fk')->references('id')->on('users')->nullOnDelete();

                $table->index(['vendor_id', 'event_inquiry_id', 'created_at'], 'ev_activities_vendor_inq_time_idx');

                // Tenant composite foreign key constraint
                $table->foreign(['event_inquiry_id', 'vendor_id'], 'ev_activities_inq_vendor_fk')
                    ->references(['id', 'vendor_id'])
                    ->on('event_inquiries')
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_inquiry_activities');
        Schema::dropIfExists('event_inquiry_notes');
        Schema::dropIfExists('event_inquiries');

        if (Schema::hasColumn('vendors', 'event_inquiry_settings')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->dropColumn('event_inquiry_settings');
            });
        }
    }
};
