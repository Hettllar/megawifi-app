<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('identity')->nullable();
            $table->string('ip_address');
            $table->integer('api_port')->default(8728);
            $table->string('api_username');
            $table->string('api_password');
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['online', 'offline', 'unknown'])->default('unknown');
            $table->timestamp('last_seen')->nullable();
            $table->string('router_os_version')->nullable();
            $table->string('board_name')->nullable();
            $table->string('serial_number')->nullable();
            $table->bigInteger('uptime')->nullable();
            $table->bigInteger('total_memory')->nullable();
            $table->bigInteger('free_memory')->nullable();
            $table->bigInteger('total_hdd')->nullable();
            $table->bigInteger('free_hdd')->nullable();
            $table->string('cpu_load')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('sync_enabled')->default(true);
            $table->integer('sync_interval')->default(60); // seconds
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routers');
    }
};
