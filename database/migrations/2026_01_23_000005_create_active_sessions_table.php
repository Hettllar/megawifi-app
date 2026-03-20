<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Active Sessions (Hotspot & PPP)
        Schema::create('active_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscriber_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('username');
            $table->string('session_id')->nullable();
            $table->enum('type', ['hotspot', 'ppp'])->default('ppp');
            $table->string('mac_address')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('caller_id')->nullable();
            $table->string('nas_port_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->bigInteger('uptime')->default(0);
            $table->bigInteger('bytes_in')->default(0);
            $table->bigInteger('bytes_out')->default(0);
            $table->string('rate_limit')->nullable();
            $table->timestamps();
            
            $table->index(['router_id', 'username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('active_sessions');
    }
};
