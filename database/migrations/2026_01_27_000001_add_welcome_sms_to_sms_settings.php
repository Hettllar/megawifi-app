<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('sms_settings', 'welcome_enabled')) {
                $table->boolean('welcome_enabled')->default(false)->after('after_expiry_days');
            }
            if (!Schema::hasColumn('sms_settings', 'welcome_message')) {
                $table->text('welcome_message')->nullable()->after('welcome_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sms_settings', function (Blueprint $table) {
            $table->dropColumn(['welcome_enabled', 'welcome_message']);
        });
    }
};
