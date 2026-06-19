<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('color', 7)->default('#3B82F6');
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('cpf', 11)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('photo_path', 500)->nullable();
            $table->enum('gender', ['M', 'F', 'NB', 'NI'])->nullable();
            $table->enum('type', ['voter', 'supporter', 'leader', 'donor', 'volunteer'])->default('voter');
            $table->tinyInteger('engagement_score')->default(3);
            $table->text('notes')->nullable();
            $table->string('profession')->nullable();
            $table->string('zip_code', 8)->nullable();
            $table->string('address')->nullable();
            $table->string('address_number', 20)->nullable();
            $table->string('neighborhood', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->char('state', 2)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('electoral_zone', 10)->nullable();
            $table->string('electoral_section', 10)->nullable();
            $table->foreignId('leader_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('mautic_id', 100)->nullable();
            $table->boolean('lgpd_consent')->default(false);
            $table->timestamp('lgpd_consent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'leader_id']);
            $table->index(['tenant_id', 'electoral_zone']);
            $table->index(['latitude', 'longitude']);
        });

        Schema::create('contact_tags', function (Blueprint $table) {
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['contact_id', 'tag_id']);
        });

        Schema::create('contact_interaction_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['note', 'call', 'visit', 'email', 'sms', 'whatsapp', 'meeting']);
            $table->text('description');
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('contact_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->integer('total_rows')->default(0);
            $table->integer('processed_rows')->default(0);
            $table->integer('error_rows')->default(0);
            $table->json('error_log')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_imports');
        Schema::dropIfExists('contact_interaction_logs');
        Schema::dropIfExists('contact_tags');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('tags');
    }
};
