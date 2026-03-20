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
        Schema::table('traffic_history', function (Blueprint $table) {
            $table->timestamp('session_start')->nullable()->after('bytes_out');
            $table->timestamp('session_end')->nullable()->after('session_start');
            $table->integer('uptime')->default(0)->after('session_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('traffic_history', function (Blueprint $table) {
            $table->dropColumn(['session_start', 'session_end', 'uptime']);
        });
    }
};
