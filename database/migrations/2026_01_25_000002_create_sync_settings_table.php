<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value')->nullable();
            $table->timestamps();
        });

        // Insert default settings
        DB::table('sync_settings')->insert([
            ['key' => 'auto_sync_enabled', 'value' => 'true', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'sync_interval', 'value' => '5', 'created_at' => now(), 'updated_at' => now()], // minutes
            ['key' => 'full_sync_interval', 'value' => '60', 'created_at' => now(), 'updated_at' => now()], // minutes
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_settings');
    }
};
