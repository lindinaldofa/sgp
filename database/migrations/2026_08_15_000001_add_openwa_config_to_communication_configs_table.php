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
            $table->dropColumn([
                'whatsapp_phone_number_id',
                'whatsapp_business_account_id',
                'whatsapp_access_token',
            ]);

            $table->string('openwa_base_url', 500)->nullable()->after('sns_sms_sender_id');
            $table->text('openwa_api_key')->nullable()->after('openwa_base_url');
            $table->string('openwa_session_id', 100)->nullable()->after('openwa_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('communication_configs', function (Blueprint $table) {
            $table->dropColumn(['openwa_base_url', 'openwa_api_key', 'openwa_session_id']);

            $table->string('whatsapp_phone_number_id', 100)->nullable();
            $table->string('whatsapp_business_account_id', 100)->nullable();
            $table->text('whatsapp_access_token')->nullable();
        });
    }
};
