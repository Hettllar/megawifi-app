<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->boolean('iptv_enabled')->default(false)->after('whatsapp_number');
            $table->string('iptv_allowed_ips')->nullable()->after('iptv_enabled')->comment('Comma-separated IP addresses or ranges');
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropColumn(['iptv_enabled', 'iptv_allowed_ips']);
        });
    }
};
