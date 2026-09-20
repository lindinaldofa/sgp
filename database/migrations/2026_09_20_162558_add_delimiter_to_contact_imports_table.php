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
            $table->string('delimiter', 1)->default(',')->after('headers');
        });
    }

    public function down(): void
    {
        Schema::table('contact_imports', function (Blueprint $table) {
            $table->dropColumn('delimiter');
        });
    }
};
