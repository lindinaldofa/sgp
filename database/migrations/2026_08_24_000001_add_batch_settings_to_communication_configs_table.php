<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_configs', function (Blueprint $table) {
            // Mirrors Mautic's batch sending: send `batch_size` messages, wait
            // `batch_delay_seconds`, repeat — spreads load instead of bursting
            // the SES/SNS/OpenWA APIs all at once.
            $table->unsignedInteger('batch_size')->default(50)->after('daily_whatsapp_limit');
            $table->unsignedInteger('batch_delay_seconds')->default(30)->after('batch_size');
        });
    }

    public function down(): void
    {
        Schema::table('communication_configs', function (Blueprint $table) {
            $table->dropColumn(['batch_size', 'batch_delay_seconds']);
        });
    }
};
