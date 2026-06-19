<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('instagram', 100)->nullable()->after('whatsapp');
            $table->string('tiktok', 100)->nullable()->after('instagram');
            $table->string('facebook', 150)->nullable()->after('tiktok');
            $table->string('twitter', 100)->nullable()->after('facebook');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['instagram', 'tiktok', 'facebook', 'twitter']);
        });
    }
};
