<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscriber_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('username');
            $table->string('session_id')->nullable();
            $table->enum('type', ['hotspot', 'ppp'])->default('ppp');
            $table->string('mac_address')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->bigInteger('duration')->default(0);
            $table->bigInteger('bytes_in')->default(0);
            $table->bigInteger('bytes_out')->default(0);
            $table->bigInteger('total_bytes')->default(0);
            $table->timestamps();
            
            $table->index(['subscriber_id', 'started_at']);
            $table->index(['router_id', 'username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_history');
    }
};
