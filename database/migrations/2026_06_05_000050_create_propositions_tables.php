<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propositions', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['bill', 'amendment', 'motion', 'requirement', 'indication', 'decree']);
            $table->string('number', 50)->nullable();
            $table->smallInteger('year');
            $table->string('title', 500);
            $table->text('summary')->nullable();
            $table->longText('full_text')->nullable();
            $table->enum('status', ['draft', 'filed', 'in_committee', 'voted', 'approved', 'vetoed', 'archived'])->default('draft');
            $table->string('theme', 100)->nullable();
            $table->foreignId('territory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('votes_for')->default(0);
            $table->integer('votes_against')->default(0);
            $table->integer('votes_abstained')->default(0);
            $table->enum('our_vote', ['for', 'against', 'abstained', 'absent'])->nullable();
            $table->text('vote_justification')->nullable();
            $table->date('filed_at')->nullable();
            $table->date('voted_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('proposition_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposition_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('path', 500);
            $table->integer('size');
            $table->enum('type', ['full_text', 'amendment', 'vote_record', 'press_release']);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('proposition_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('old_status', 50)->nullable();
            $table->string('new_status', 50);
            $table->text('note')->nullable();
            $table->timestamp('changed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposition_status_history');
        Schema::dropIfExists('proposition_attachments');
        Schema::dropIfExists('propositions');
    }
};
