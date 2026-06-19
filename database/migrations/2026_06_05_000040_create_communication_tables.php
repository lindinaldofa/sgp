<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->enum('channel', ['email', 'sms', 'whatsapp']);
            $table->string('subject')->nullable();
            $table->longText('body_html')->nullable();
            $table->text('body_text')->nullable();
            $table->json('variables')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'name', 'channel']);
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('channel', ['email', 'sms', 'whatsapp']);
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'cancelled', 'failed'])->default('draft');
            $table->string('subject')->nullable();
            $table->longText('body_html')->nullable();
            $table->text('body_text')->nullable();
            $table->string('whatsapp_template_name', 100)->nullable();
            $table->string('whatsapp_template_lang', 10)->default('pt_BR');
            $table->json('segment_filters')->nullable();
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('delivered_count')->default(0);
            $table->integer('opened_count')->default(0);
            $table->integer('clicked_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'sent', 'delivered', 'opened', 'clicked', 'failed', 'unsubscribed'])->default('pending');
            $table->string('external_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index('contact_id');
        });

        Schema::create('communication_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('aws_region', 20)->default('us-east-1');
            $table->string('ses_from_email')->nullable();
            $table->string('ses_from_name')->nullable();
            $table->string('sns_region', 20)->nullable();
            $table->string('sns_sms_sender_id', 20)->nullable();
            $table->string('whatsapp_phone_number_id', 100)->nullable();
            $table->string('whatsapp_business_account_id', 100)->nullable();
            $table->text('whatsapp_access_token')->nullable();
            $table->integer('daily_email_limit')->default(10000);
            $table->integer('daily_sms_limit')->default(500);
            $table->integer('daily_whatsapp_limit')->default(5000);
            $table->timestamps();
        });

        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->enum('trigger', ['birthday', 'new_contact', 'status_change', 'anniversary']);
            $table->enum('channel', ['email', 'sms', 'whatsapp']);
            $table->foreignId('template_id')->constrained('message_templates');
            $table->boolean('is_active')->default(true);
            $table->time('send_time')->nullable();
            $table->tinyInteger('days_before')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
        Schema::dropIfExists('communication_configs');
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('message_templates');
    }
};
