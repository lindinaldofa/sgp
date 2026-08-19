<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_template_name', 'whatsapp_template_lang']);

            $table->string('whatsapp_message_type', 20)->default('text')->after('body_text');
            $table->string('media_path', 500)->nullable()->after('whatsapp_message_type');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_message_type', 'media_path']);

            $table->string('whatsapp_template_name', 100)->nullable();
            $table->string('whatsapp_template_lang', 10)->default('pt_BR');
        });
    }
};
