<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_history', function (Blueprint $table) {
            $table->string('um_session_id')->nullable()->after('session_id');
            $table->string('source')->default('sync')->after('total_bytes'); // sync|offload
            $table->index('um_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('session_history', function (Blueprint $table) {
            $table->dropIndex(['um_session_id']);
            $table->dropColumn(['um_session_id', 'source']);
        });
    }
};