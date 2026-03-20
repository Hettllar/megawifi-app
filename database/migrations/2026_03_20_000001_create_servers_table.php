<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('hostname');           // IP أو دومين أو WireGuard IP
            $table->integer('ssh_port')->default(22);
            $table->string('ssh_username')->default('root');
            $table->string('ssh_password')->nullable(); // مشفرة
            $table->string('location')->nullable();
            $table->string('description')->nullable();
            $table->enum('status', ['online', 'offline', 'unknown'])->default('unknown');
            $table->timestamp('last_seen')->nullable();
            $table->boolean('is_active')->default(true);

            // الوصول الخارجي عبر SSH port forwarding
            $table->string('public_host')->nullable(); // syrianew.live أو غيره
            $table->integer('public_port')->nullable(); // البورت الخارجي المعين

            // إحصاءات
            $table->integer('connection_errors')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();

            // معلومات النظام
            $table->string('os_info')->nullable();
            $table->string('hostname_resolved')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
