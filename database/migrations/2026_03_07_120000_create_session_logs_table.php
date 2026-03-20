<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscriber_id')->nullable()->index();
            $table->unsignedBigInteger('router_id')->index();
            $table->string('username', 100);
            $table->string('ip_address', 45)->nullable();
            $table->string('mac_address', 17)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['router_id', 'username', 'started_at'], 'uniq_session_log');
            $table->index('created_at');

            $table->foreign('subscriber_id')->references('id')->on('subscribers')->nullOnDelete();
            $table->foreign('router_id')->references('id')->on('routers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_logs');
    }
};
