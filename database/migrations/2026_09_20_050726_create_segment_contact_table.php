<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('segment_contact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('segment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->enum('source', ['manual', 'filter'])->default('filter');
            $table->timestamps();
            $table->unique(['segment_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segment_contact');
    }
};
