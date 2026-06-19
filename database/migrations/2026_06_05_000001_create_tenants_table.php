<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->enum('plan', ['essential', 'advanced', 'strategic'])->default('essential');
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->bigInteger('storage_used')->default(0);
            $table->bigInteger('storage_limit')->default(5368709120);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
