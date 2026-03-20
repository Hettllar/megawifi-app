<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Subscribers / Customers
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_plan_id')->nullable()->constrained()->onDelete('set null');
            $table->string('username')->index();
            $table->string('password');
            $table->string('full_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('national_id')->nullable();
            $table->enum('type', ['hotspot', 'ppp', 'both'])->default('ppp');
            $table->enum('status', ['active', 'disabled', 'expired', 'suspended'])->default('active');
            $table->string('mikrotik_id')->nullable(); // ID in MikroTik
            $table->string('profile')->nullable();
            $table->string('caller_id')->nullable(); // MAC binding
            $table->string('remote_address')->nullable(); // Static IP
            $table->string('local_address')->nullable();
            $table->timestamp('expiration_date')->nullable();
            $table->timestamp('first_login')->nullable();
            $table->timestamp('last_login')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->bigInteger('bytes_in')->default(0);
            $table->bigInteger('bytes_out')->default(0);
            $table->bigInteger('total_bytes')->default(0);
            $table->bigInteger('data_limit')->nullable();
            $table->integer('uptime_used')->default(0); // seconds
            $table->integer('uptime_limit')->nullable(); // seconds
            $table->decimal('balance', 10, 2)->default(0);
            $table->decimal('total_paid', 10, 2)->default(0);
            $table->text('comment')->nullable();
            $table->boolean('is_synced')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->unique(['router_id', 'username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};
