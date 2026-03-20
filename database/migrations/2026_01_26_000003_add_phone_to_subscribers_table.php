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
        Schema::table('subscribers', function (Blueprint $table) {
            if (!Schema::hasColumn('subscribers', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }
            if (!Schema::hasColumn('subscribers', 'sms_enabled')) {
                $table->boolean('sms_enabled')->default(true)->after('phone');
            }
            if (!Schema::hasColumn('subscribers', 'last_sms_sent_at')) {
                $table->timestamp('last_sms_sent_at')->nullable()->after('sms_enabled');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropColumn(['phone', 'sms_enabled', 'last_sms_sent_at']);
        });
    }
};
