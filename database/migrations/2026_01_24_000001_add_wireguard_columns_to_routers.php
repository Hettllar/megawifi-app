<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->string('wg_private_key')->nullable()->after('api_password');
            $table->string('wg_public_key')->nullable()->after('wg_private_key');
            $table->string('wg_client_ip')->nullable()->after('wg_public_key');
            $table->boolean('wg_enabled')->default(false)->after('wg_client_ip');
            $table->timestamp('wg_last_handshake')->nullable()->after('wg_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->dropColumn([
                'wg_private_key',
                'wg_public_key', 
                'wg_client_ip',
                'wg_enabled',
                'wg_last_handshake'
            ]);
        });
    }
};
