<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->string('connection_type')->default('direct')->after('wg_last_handshake');
            // direct = IP مباشر, wireguard = WireGuard VPN, zerotier = ZeroTier
            $table->string('zt_network_id')->nullable()->after('connection_type');
            $table->string('zt_member_id')->nullable()->after('zt_network_id');
            $table->string('zt_ip')->nullable()->after('zt_member_id');
            $table->boolean('zt_enabled')->default(false)->after('zt_ip');
            $table->timestamp('zt_last_seen')->nullable()->after('zt_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->dropColumn([
                'connection_type',
                'zt_network_id',
                'zt_member_id',
                'zt_ip',
                'zt_enabled',
                'zt_last_seen',
            ]);
        });
    }
};
