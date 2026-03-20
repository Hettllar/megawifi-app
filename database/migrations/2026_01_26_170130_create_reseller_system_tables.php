<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // جدول صلاحيات الوكيل على كل راوتر
        Schema::create('reseller_router_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reseller_id');
            $table->unsignedBigInteger('router_id');
            
            // صلاحيات الهوت سبوت
            $table->boolean('can_create_hotspot')->default(false);
            $table->boolean('can_edit_hotspot')->default(false);
            $table->boolean('can_delete_hotspot')->default(false);
            $table->boolean('can_enable_disable_hotspot')->default(false);
            
            // صلاحيات PPP
            $table->boolean('can_create_ppp')->default(false);
            $table->boolean('can_edit_ppp')->default(false);
            $table->boolean('can_delete_ppp')->default(false);
            $table->boolean('can_enable_disable_ppp')->default(false);
            
            // صلاحيات UserManager
            $table->boolean('can_create_usermanager')->default(false);
            $table->boolean('can_edit_usermanager')->default(false);
            $table->boolean('can_delete_usermanager')->default(false);
            
            // صلاحيات أخرى
            $table->boolean('can_view_reports')->default(true);
            $table->boolean('can_generate_vouchers')->default(false);
            
            $table->timestamps();
            
            $table->foreign('reseller_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('router_id')->references('id')->on('routers')->onDelete('cascade');
            $table->unique(['reseller_id', 'router_id']);
        });

        // جدول تسعير الخدمات للوكلاء
        Schema::create('reseller_pricing', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('router_id');
            $table->string('service_type'); // hotspot, ppp, usermanager
            $table->string('pricing_type'); // per_gb, per_day, per_month, fixed
            $table->decimal('price_per_unit', 10, 2)->default(0); // السعر لكل وحدة
            $table->string('currency')->default('SYP'); // العملة
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->foreign('router_id')->references('id')->on('routers')->onDelete('cascade');
        });

        // جدول معاملات الوكلاء (الشحن والخصم)
        Schema::create('reseller_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reseller_id');
            $table->unsignedBigInteger('admin_id')->nullable(); // من قام بالعملية
            $table->enum('type', ['deposit', 'withdraw', 'purchase', 'refund', 'commission']);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('description');
            $table->string('reference')->nullable(); // مرجع (رقم المشترك مثلاً)
            $table->unsignedBigInteger('subscriber_id')->nullable();
            $table->timestamps();
            
            $table->foreign('reseller_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('admin_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('subscriber_id')->references('id')->on('subscribers')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_transactions');
        Schema::dropIfExists('reseller_pricing');
        Schema::dropIfExists('reseller_router_permissions');
    }
};
