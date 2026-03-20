<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reseller_router_permissions', function (Blueprint $table) {
            // إضافة صلاحية تجديد اليوزر مانجر
            $table->boolean('can_renew_usermanager')->default(false)->after('can_delete_usermanager');
            // إضافة صلاحية تفعيل/تعطيل اليوزر مانجر
            $table->boolean('can_enable_disable_usermanager')->default(false)->after('can_renew_usermanager');
        });
    }

    public function down(): void
    {
        Schema::table('reseller_router_permissions', function (Blueprint $table) {
            $table->dropColumn(['can_renew_usermanager', 'can_enable_disable_usermanager']);
        });
    }
};
