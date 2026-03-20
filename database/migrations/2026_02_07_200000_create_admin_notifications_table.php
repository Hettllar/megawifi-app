<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // renewal, payment, new_subscriber, etc
            $table->string('title');
            $table->text('message');
            $table->string('icon')->default('fa-bell');
            $table->string('color')->default('blue');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade'); // المستخدم المرسل (الوكيل)
            $table->foreignId('subscriber_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('router_id')->nullable()->constrained()->onDelete('cascade');
            $table->json('data')->nullable(); // بيانات إضافية
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            $table->index(['is_read', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};
