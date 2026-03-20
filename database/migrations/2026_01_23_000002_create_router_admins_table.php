<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Router Admins - linking users to routers they can manage
        Schema::create('router_admins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('router_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['super_admin', 'admin', 'operator', 'viewer'])->default('operator');
            $table->boolean('can_add_users')->default(false);
            $table->boolean('can_delete_users')->default(false);
            $table->boolean('can_edit_users')->default(true);
            $table->boolean('can_view_reports')->default(true);
            $table->boolean('can_manage_hotspot')->default(false);
            $table->boolean('can_manage_ppp')->default(false);
            $table->timestamps();
            
            $table->unique(['user_id', 'router_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('router_admins');
    }
};
