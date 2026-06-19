<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('icon', 50)->nullable();
            $table->string('color', 7)->default('#3B82F6');
            $table->foreignId('parent_id')->nullable()->constrained('service_categories')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('category_id')->constrained('service_categories');
            $table->string('subject');
            $table->text('description')->nullable();
            $table->enum('status', ['new', 'in_progress', 'waiting', 'resolved', 'archived'])->default('new');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->enum('source', ['manual', 'whatsapp', 'email', 'phone', 'portal', 'in_person'])->default('manual');
            $table->timestamp('resolved_at')->nullable();
            $table->date('due_date')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('mautic_ticket_id', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('service_request_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('old_status', 50)->nullable();
            $table->string('new_status', 50)->nullable();
            $table->text('note')->nullable();
            $table->boolean('notify_contact')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('service_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('path', 500);
            $table->integer('size');
            $table->string('mime_type', 100);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_attachments');
        Schema::dropIfExists('service_request_updates');
        Schema::dropIfExists('service_requests');
        Schema::dropIfExists('service_categories');
    }
};
