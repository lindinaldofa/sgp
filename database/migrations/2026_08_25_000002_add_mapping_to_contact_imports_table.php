<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_imports', function (Blueprint $table) {
            $table->foreignId('tag_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->json('column_mapping')->nullable()->after('tag_id');
            $table->json('headers')->nullable()->after('column_mapping');

            $table->enum('status', ['pending', 'mapping', 'processing', 'completed', 'failed'])
                ->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('contact_imports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tag_id');
            $table->dropColumn(['column_mapping', 'headers']);

            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])
                ->default('pending')->change();
        });
    }
};
