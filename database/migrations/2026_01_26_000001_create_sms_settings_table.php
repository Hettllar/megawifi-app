<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sms_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained()->onDelete('cascade');
            $table->string('usb_port')->default('usb1'); // USB port name in MikroTik
            $table->string('country_code')->default('+218'); // Libya
            $table->boolean('is_enabled')->default(false);
            $table->integer('reminder_days_before')->default(3); // Days before expiry
            $table->text('reminder_message')->nullable(); // SMS template
            $table->string('sender_name')->nullable(); // Optional sender ID
            $table->time('send_time')->default('09:00:00'); // Time to send reminders
            $table->boolean('send_on_expiry')->default(true); // Send on expiry day
            $table->boolean('send_after_expiry')->default(false); // Send after expiry
            $table->integer('after_expiry_days')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_settings');
    }
};
