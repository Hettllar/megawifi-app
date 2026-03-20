<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Service Plans / Profiles
        Schema::create('service_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('mikrotik_profile_name'); // Name in MikroTik
            $table->enum('type', ['hotspot', 'ppp', 'both'])->default('both');
            $table->string('rate_limit')->nullable(); // e.g., "10M/10M"
            $table->string('upload_speed')->nullable();
            $table->string('download_speed')->nullable();
            $table->integer('session_timeout')->nullable(); // minutes
            $table->integer('idle_timeout')->nullable(); // minutes
            $table->integer('keepalive_timeout')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->enum('validity_type', ['unlimited', 'days', 'hours', 'minutes', 'bytes'])->default('days');
            $table->integer('validity_value')->nullable();
            $table->bigInteger('data_limit')->nullable(); // bytes
            $table->boolean('shared_users')->default(false);
            $table->integer('shared_users_count')->default(1);
            $table->string('address_pool')->nullable();
            $table->string('local_address')->nullable();
            $table->string('remote_address')->nullable();
            $table->string('dns_server')->nullable();
            $table->text('scripts')->nullable(); // on-login, on-logout scripts
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_plans');
    }
};
