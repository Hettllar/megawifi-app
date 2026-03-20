<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vpn_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name')->default('Mobile VPN');
            $table->string('client_private_key');
            $table->string('client_public_key');
            $table->string('client_ip');
            $table->string('server_public_key');
            $table->string('server_endpoint');
            $table->string('dns')->default('8.8.8.8');
            $table->string('allowed_ips')->default('10.10.0.0/24');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vpn_configs');
    }
};