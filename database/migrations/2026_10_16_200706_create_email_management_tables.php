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
        // 1. SMTP / Transport Connections
        Schema::create('email_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('transport')->default('smtp');
            $table->string('host')->nullable();
            $table->integer('port')->default(587);
            $table->string('encryption')->nullable(); // tls, ssl, starttls, none
            $table->string('username')->nullable();
            $table->text('password')->nullable(); // encrypted cast
            $table->integer('timeout')->default(15);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_default')->default(false)->index();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable(); // success, failed
            $table->text('last_error')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Sender Identities
        Schema::create('email_sender_identities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->index();
            $table->text('description')->nullable();
            $table->string('reply_to_email')->nullable();
            $table->string('reply_to_name')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_default')->default(false)->index();
            $table->foreignId('email_connection_id')->nullable()->constrained('email_connections')->nullOnDelete();
            $table->jsonb('assigned_categories')->nullable();
            $table->string('verification_status')->default('verified'); // verified, pending, unverified
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 3. Email Routing Rules
        Schema::create('email_routing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('category')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('sender_identity_id')->nullable()->constrained('email_sender_identities')->nullOnDelete();
            $table->foreignId('email_connection_id')->nullable()->constrained('email_connections')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('priority')->default(0);
            $table->string('fallback_behavior')->default('default_sender'); // default_sender, system_fallback, fail
            $table->timestamps();
        });

        // 4. Email Templates (Structured Content)
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('category')->index();
            $table->string('subject');
            $table->text('intro_text')->nullable();
            $table->string('cta_text')->nullable();
            $table->string('cta_url_template')->nullable();
            $table->text('footer_text')->nullable();
            $table->string('support_contact')->nullable();
            $table->string('branding_logo_url')->nullable();
            $table->string('branding_primary_color')->nullable();
            $table->jsonb('allowed_variables')->nullable();
            $table->jsonb('translations')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('version')->default(1);
            $table->timestamps();
        });

        // 5. Email Logs (Auditable & Searchable)
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('recipient_email')->index();
            $table->string('recipient_name')->nullable();
            $table->string('subject');
            $table->string('category')->index();
            $table->string('mailable_class')->nullable()->index();
            $table->foreignId('sender_identity_id')->nullable()->constrained('email_sender_identities')->nullOnDelete();
            $table->string('from_email')->index();
            $table->string('from_name')->nullable();
            $table->string('reply_to_email')->nullable();
            $table->foreignId('email_connection_id')->nullable()->constrained('email_connections')->nullOnDelete();
            $table->string('connection_name')->nullable();
            $table->string('transport')->nullable();
            $table->string('status')->default('queued')->index(); // queued, processing, sent, delivered, failed, bounced, complained, cancelled
            $table->string('provider_message_id')->nullable()->index();
            $table->string('queue_job_id')->nullable()->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['category', 'created_at']);
            $table->index(['recipient_email', 'created_at']);
            $table->index(['vendor_id', 'created_at']);
        });

        // 6. Delivery Attempts
        Schema::create('email_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_log_id')->constrained('email_logs')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('status');
            $table->string('connection_name')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['email_log_id', 'attempt_number']);
        });

        // 7. Provider Webhook Events
        Schema::create('email_provider_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_log_id')->nullable()->constrained('email_logs')->nullOnDelete();
            $table->string('provider');
            $table->string('event_type')->index(); // delivered, bounced, complained, rejected
            $table->string('provider_message_id')->nullable()->index();
            $table->string('recipient_email')->nullable()->index();
            $table->jsonb('payload')->nullable();
            $table->boolean('signature_verified')->default(false);
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // 8. Configuration Audit Trail
        Schema::create('email_configuration_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('target_name')->nullable();
            $table->jsonb('changes')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_configuration_audits');
        Schema::dropIfExists('email_provider_events');
        Schema::dropIfExists('email_attempts');
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('email_routing_rules');
        Schema::dropIfExists('email_sender_identities');
        Schema::dropIfExists('email_connections');
    }
};
