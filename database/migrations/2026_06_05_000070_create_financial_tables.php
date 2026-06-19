<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('total_monthly', 12, 2);
            $table->smallInteger('year');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('category', ['staff', 'office', 'travel', 'communication', 'consulting', 'events', 'material', 'other']);
            $table->string('description', 500);
            $table->string('supplier')->nullable();
            $table->string('supplier_cnpj', 14)->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->string('receipt_path', 500)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('donors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('cpf_cnpj', 14)->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('donated_at');
            $table->string('campaign', 100)->nullable();
            $table->boolean('receipt_issued')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donors');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('budget_allowances');
    }
};
